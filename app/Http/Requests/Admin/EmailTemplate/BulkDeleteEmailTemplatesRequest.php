<?php

namespace App\Http\Requests\Admin\EmailTemplate;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteEmailTemplatesRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:email_templates,id'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'At least one email template ID must be provided.',
            'ids.array' => 'The ids field must be an array of email template IDs.',
            'ids.min' => 'At least one email template ID must be provided.',
            'ids.max' => 'Cannot bulk delete more than 100 email templates at once.',
            'ids.*.integer' => 'Each email template ID must be an integer.',
            'ids.*.distinct' => 'Duplicate email template IDs are not allowed in the selection.',
            'ids.*.exists' => 'One or more selected email template IDs do not exist.',
        ];
    }
}
