<?php

namespace App\Http\Requests\Admin\Role;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkDeleteRoleRequest extends FormRequest
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
            'ids.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'At least one role ID must be provided.',
            'ids.array' => 'The ids field must be an array of role IDs.',
            'ids.min' => 'At least one role ID must be provided.',
            'ids.max' => 'Cannot bulk delete more than 100 roles at once.',
            'ids.*.required' => 'Role ID is required.',
            'ids.*.integer' => 'Each role ID must be an integer.',
            'ids.*.distinct' => 'Duplicate role IDs are not allowed in the selection.',
            'ids.*.exists' => 'One or more selected role IDs do not exist.',
        ];
    }

    /**
     * Get validated integer IDs.
     *
     * @return list<int>
     */
    public function validatedIds(): array
    {
        return array_map('intval', (array) $this->input('ids', []));
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
