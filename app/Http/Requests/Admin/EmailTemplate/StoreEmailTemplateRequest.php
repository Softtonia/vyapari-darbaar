<?php

namespace App\Http\Requests\Admin\EmailTemplate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEmailTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Z0-9_]+$/',
                'unique:email_templates,key',
            ],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $key = (string) $this->input('key');
            $subject = (string) $this->input('subject');
            $body = (string) $this->input('body');

            if (str_contains($subject, '{{TemporaryPassword}}')) {
                $validator->errors()->add(
                    'subject',
                    'The {{TemporaryPassword}} placeholder is strictly forbidden in the email subject.'
                );
            }

            if ($key === 'USER_ACCOUNT_CREATED') {
                if (! str_contains($body, '{{Username}}')) {
                    $validator->errors()->add(
                        'body',
                        'The {{Username}} placeholder is required in the USER_ACCOUNT_CREATED template body.'
                    );
                }

                if (! str_contains($body, '{{TemporaryPassword}}')) {
                    $validator->errors()->add(
                        'body',
                        'The {{TemporaryPassword}} placeholder is required in the USER_ACCOUNT_CREATED template body.'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'The template key must only contain uppercase letters, numbers, and underscores (e.g. USER_ACCOUNT_CREATED).',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
