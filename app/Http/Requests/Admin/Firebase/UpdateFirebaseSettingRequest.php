<?php

namespace App\Http\Requests\Admin\Firebase;

use App\Models\FirebaseSetting;
use App\Services\FirebaseWebConfigParserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use Throwable;

class UpdateFirebaseSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        // 1. Normalize status boolean
        if ($this->has('status')) {
            $raw = $this->input('status');
            if (is_string($raw)) {
                $lower = strtolower(trim($raw));
                if (in_array($lower, ['1', 'true', 'active', 'on', 'yes'], true)) {
                    $this->merge(['status' => true]);
                } elseif (in_array($lower, ['0', 'false', 'inactive', 'off', 'no'], true)) {
                    $this->merge(['status' => false]);
                }
            }
        }

        // 2. Read service_account file into service_account_json if uploaded
        if ($this->hasFile('service_account')) {
            $file = $this->file('service_account');
            if ($file && $file->isValid()) {
                $content = @file_get_contents($file->getRealPath());
                if ($content !== false && trim($content) !== '') {
                    $this->merge(['service_account_json' => $content]);
                }
            }
        }

        // 3. Parse web_config if provided
        $webConfig = $this->input('web_config');
        if (! empty($webConfig)) {
            try {
                $parser = app(FirebaseWebConfigParserService::class);
                $parsed = $parser->parse($webConfig);
                $this->merge([
                    'api_key' => $parsed['apiKey'],
                    'auth_domain' => $parsed['authDomain'],
                    'project_id' => $parsed['projectId'],
                    'storage_bucket' => $parsed['storageBucket'],
                    'messaging_sender_id' => $parsed['messagingSenderId'],
                    'app_id' => $parsed['appId'],
                    '_web_config_parsed' => true,
                ]);
            } catch (Throwable) {
                $this->merge(['_web_config_invalid' => true]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'web_config' => ['sometimes', 'nullable'],
            'api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'auth_domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'storage_bucket' => ['sometimes', 'nullable', 'string', 'max:255'],
            'messaging_sender_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'app_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vapid_key' => ['required', 'string'],
            'status' => ['required', 'boolean'],
            'service_account' => ['nullable', 'file', 'max:512'],
            'service_account_json' => ['nullable'],
        ];
    }

    /**
     * Custom user-friendly validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'web_config.required' => 'Paste the Firebase Web App configuration.',
            'vapid_key.required' => 'Paste the Web Push certificate key from Firebase Cloud Messaging.',
            'service_account.file' => 'The selected Service Account JSON is invalid.',
            'service_account.max' => 'The Service Account JSON file must not exceed 512 kilobytes.',
            'status.required' => 'The status field is required.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $existing = FirebaseSetting::query()->find(1);
            $hasExistingServiceAccount = $existing && ! empty($existing->service_account_json);

            // 1. Validate web_config presence and parsing
            $rawWebConfig = $this->input('web_config');
            $hasDirectApiKey = ! empty($this->input('api_key')) && ! empty($this->input('project_id'));

            if (empty($rawWebConfig) && ! $hasDirectApiKey) {
                $validator->errors()->add('web_config', 'Paste the Firebase Web App configuration.');
            } elseif ($this->input('_web_config_invalid') === true) {
                $validator->errors()->add('web_config', 'We could not read the Firebase Web App configuration.');
            }

            $addSaError = function (string $message) use ($validator) {
                $validator->errors()->add('service_account', $message);
                $validator->errors()->add('service_account_json', $message);
            };

            // 2. Read and validate service account (file upload or json string fallback)
            $serviceAccountContent = null;

            if ($this->hasFile('service_account')) {
                /** @var UploadedFile $file */
                $file = $this->file('service_account');
                if (! $file->isValid()) {
                    $addSaError('The selected Service Account JSON is invalid.');
                    return;
                }

                $content = @file_get_contents($file->getRealPath());
                if ($content === false || trim($content) === '') {
                    $addSaError('The selected Service Account JSON is invalid.');
                    return;
                }

                $serviceAccountContent = $content;
            } elseif ($this->filled('service_account_json')) {
                $rawString = $this->input('service_account_json');
                if (trim((string) $rawString) !== '********') {
                    $serviceAccountContent = (string) $rawString;
                }
            }

            // If first time configuration and no service account provided
            if (! $hasExistingServiceAccount && empty($serviceAccountContent)) {
                $addSaError('Upload the Service Account JSON downloaded from Firebase.');
                return;
            }

            // If a service account is provided, validate its structure and cross-check project ID
            if (! empty($serviceAccountContent)) {
                $decoded = json_decode($serviceAccountContent, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                    $addSaError('The selected Service Account JSON is invalid.');
                    return;
                }

                $requiredKeys = ['type', 'project_id', 'private_key', 'client_email', 'token_uri'];
                foreach ($requiredKeys as $key) {
                    if (empty($decoded[$key]) || ! is_string($decoded[$key])) {
                        $addSaError('The selected Service Account JSON is invalid.');
                        return;
                    }
                }

                if ($decoded['type'] !== 'service_account') {
                    $addSaError('The selected Service Account JSON is invalid.');
                    return;
                }

                // Cross-check project_id against Web Config projectId
                $projectId = (string) $this->input('project_id');
                if (! empty($projectId) && $decoded['project_id'] !== $projectId) {
                    $addSaError('The Firebase Web App and Service Account belong to different projects.');
                    return;
                }

                // Merge the verified raw JSON string so the service/controller can store it encrypted
                $this->merge(['service_account_json' => $serviceAccountContent]);
            }
        });
    }
}
