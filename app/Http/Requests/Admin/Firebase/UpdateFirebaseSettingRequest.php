<?php

namespace App\Http\Requests\Admin\Firebase;

use App\Models\FirebaseSetting;
use App\Services\FirebaseWebConfigParserService;
use Illuminate\Foundation\Http\FormRequest;
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
        // If web_config is supplied (as string snippet or array/json), parse it automatically
        if ($this->has('web_config')) {
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
                    ]);
                } catch (Throwable) {
                    // Let validation rules flag missing fields if parsing fails
                }
            }
        }

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
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $existing = FirebaseSetting::query()->find(1);
        $hasServiceAccount = $existing && ! empty($existing->service_account_json);

        $serviceAccountRules = $hasServiceAccount
            ? ['nullable', 'string']
            : ['required', 'string'];

        return [
            'web_config' => ['nullable'],
            'api_key' => ['required', 'string', 'max:255'],
            'auth_domain' => ['required', 'string', 'max:255'],
            'project_id' => ['required', 'string', 'max:255'],
            'storage_bucket' => ['nullable', 'string', 'max:255'],
            'messaging_sender_id' => ['required', 'string', 'max:255'],
            'app_id' => ['required', 'string', 'max:255'],
            'vapid_key' => ['required', 'string'],
            'service_account_json' => $serviceAccountRules,
            'status' => ['required', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $serviceAccountRaw = $this->input('service_account_json');

            // If empty or password placeholder, retain existing if allowed
            if ($serviceAccountRaw === null || trim((string) $serviceAccountRaw) === '' || trim((string) $serviceAccountRaw) === '********') {
                return;
            }

            $decoded = json_decode((string) $serviceAccountRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                $validator->errors()->add('service_account_json', 'The service account must be a valid JSON string.');

                return;
            }

            $requiredKeys = ['type', 'project_id', 'private_key', 'client_email', 'token_uri'];
            foreach ($requiredKeys as $key) {
                if (empty($decoded[$key]) || ! is_string($decoded[$key])) {
                    $validator->errors()->add('service_account_json', "The service account JSON is missing the required '{$key}' field.");

                    return;
                }
            }

            if ($decoded['type'] !== 'service_account') {
                $validator->errors()->add('service_account_json', "The service account JSON 'type' must be 'service_account'.");

                return;
            }

            $projectId = (string) $this->input('project_id');
            if ($decoded['project_id'] !== $projectId) {
                $validator->errors()->add('service_account_json', "The service account project_id '{$decoded['project_id']}' does not match the configured project_id '{$projectId}'.");
            }
        });
    }
}
