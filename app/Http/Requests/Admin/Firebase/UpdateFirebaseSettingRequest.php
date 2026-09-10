<?php

namespace App\Http\Requests\Admin\Firebase;

use App\Models\FirebaseSetting;
use App\Services\Firebase\FirebaseWebConfigParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFirebaseSettingRequest extends FormRequest
{
    /**
     * Parsed and normalized web configuration.
     *
     * @var array<string, string|null>|null
     */
    protected ?array $parsedWebConfig = null;

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

        $parser = app(FirebaseWebConfigParser::class);

        if ($this->has('web_config')) {
            $rawWebConfig = $this->input('web_config');
            $this->parsedWebConfig = $parser->parse($rawWebConfig);
        } else {
            // Backward compatibility fallback: check for individual fields
            $this->parsedWebConfig = $parser->parse($this->all());
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

        $rules = [
            'vapid_key' => ['required', 'string'],
            'service_account_json' => $serviceAccountRules,
            'status' => ['required', 'boolean'],
        ];

        // If web_config is passed, it must not be empty
        if ($this->has('web_config')) {
            $rules['web_config'] = ['required'];
        }

        return $rules;
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'web_config.required' => 'Paste your Firebase Web App configuration.',
            'vapid_key.required' => 'Paste the Web Push certificate key from Firebase Cloud Messaging settings.',
            'service_account_json.required' => 'The Service Account JSON is invalid. Download a fresh JSON key from Firebase Project Settings → Service Accounts.',
            'status.required' => 'The status field is required.',
            'status.boolean' => 'The status field must be true or false.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parsed = $this->parsedWebConfig ?? [];

            $hasAnyField = ! empty($parsed['api_key'])
                || ! empty($parsed['auth_domain'])
                || ! empty($parsed['project_id'])
                || ! empty($parsed['messaging_sender_id'])
                || ! empty($parsed['app_id']);

            if (! $hasAnyField) {
                if ($this->has('web_config')) {
                    $validator->errors()->add(
                        'web_config',
                        "We couldn't read the Firebase configuration. Copy it again from Firebase Project Settings → Your Apps → SDK setup and configuration."
                    );
                } else {
                    $validator->errors()->add('web_config', 'Paste your Firebase Web App configuration.');
                }

                return;
            }

            // Check each required field
            if (empty($parsed['api_key'])) {
                $validator->errors()->add('web_config', 'Firebase API Key could not be detected in the pasted Web App configuration.');
            }

            if (empty($parsed['auth_domain'])) {
                $validator->errors()->add('web_config', 'Firebase Auth Domain could not be detected.');
            }

            if (empty($parsed['project_id'])) {
                $validator->errors()->add('web_config', 'Firebase Project ID could not be detected.');
            }

            if (empty($parsed['messaging_sender_id'])) {
                $validator->errors()->add('web_config', 'Firebase Messaging Sender ID could not be detected.');
            }

            if (empty($parsed['app_id'])) {
                $validator->errors()->add('web_config', 'Firebase App ID could not be detected.');
            }

            // Validate service account if provided
            $serviceAccountRaw = $this->input('service_account_json');
            $shouldRetain = $serviceAccountRaw === null
                || trim((string) $serviceAccountRaw) === ''
                || trim((string) $serviceAccountRaw) === '********';

            if ($shouldRetain) {
                return;
            }

            $decoded = json_decode((string) $serviceAccountRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                $validator->errors()->add('service_account_json', 'The Service Account JSON is invalid. Download a fresh JSON key from Firebase Project Settings → Service Accounts.');

                return;
            }

            $requiredKeys = ['type', 'project_id', 'private_key', 'client_email', 'token_uri'];
            foreach ($requiredKeys as $key) {
                if (empty($decoded[$key]) || ! is_string($decoded[$key])) {
                    $validator->errors()->add('service_account_json', 'The Service Account JSON is invalid. Download a fresh JSON key from Firebase Project Settings → Service Accounts.');

                    return;
                }
            }

            if ($decoded['type'] !== 'service_account') {
                $validator->errors()->add('service_account_json', 'The Service Account JSON is invalid. Download a fresh JSON key from Firebase Project Settings → Service Accounts.');

                return;
            }

            // Cross-validate project_id between Web Config and Service Account
            $webProjectId = $parsed['project_id'] ?? null;
            if ($webProjectId !== null && $decoded['project_id'] !== $webProjectId) {
                $validator->errors()->add('service_account_json', 'The Firebase Web App and Service Account belong to different Firebase projects.');
            }
        });
    }

    /**
     * Get the validated data normalized for storage.
     *
     * @param  array|int|string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null): mixed
    {
        $parsed = $this->parsedWebConfig ?? [];

        $normalized = [
            'api_key' => $parsed['api_key'] ?? (string) $this->input('api_key'),
            'auth_domain' => $parsed['auth_domain'] ?? (string) $this->input('auth_domain'),
            'project_id' => $parsed['project_id'] ?? (string) $this->input('project_id'),
            'storage_bucket' => $parsed['storage_bucket'] ?? $this->input('storage_bucket'),
            'messaging_sender_id' => $parsed['messaging_sender_id'] ?? (string) $this->input('messaging_sender_id'),
            'app_id' => $parsed['app_id'] ?? (string) $this->input('app_id'),
            'vapid_key' => (string) $this->input('vapid_key'),
            'service_account_json' => $this->input('service_account_json'),
            'status' => (bool) $this->input('status'),
        ];

        if ($key !== null) {
            return data_get($normalized, $key, $default);
        }

        return $normalized;
    }
}
