<?php

namespace App\Http\Requests\Admin\EmailTemplate;

use App\Models\EmailTemplate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateEmailTemplateRequest extends FormRequest
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
            /** @var EmailTemplate|null $template */
            $template = $this->route('emailTemplate');

            if ($this->has('key')) {
                $requestedKey = (string) $this->input('key');
                if ($template && $requestedKey !== $template->key) {
                    $validator->errors()->add(
                        'key',
                        'The template key is immutable and cannot be modified.'
                    );
                }
            }

            $subject = (string) $this->input('subject');
            $body = (string) $this->input('body');

            if (str_contains($subject, '{{TemporaryPassword}}')) {
                $validator->errors()->add(
                    'subject',
                    'The {{TemporaryPassword}} placeholder is strictly forbidden in the email subject.'
                );
            }

            if ($template && $template->key === 'USER_ACCOUNT_CREATED') {
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
