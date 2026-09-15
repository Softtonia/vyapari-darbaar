<?php

namespace App\Http\Requests\Admin\Role;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class StoreRoleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
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
            'name.required' => 'The role name is required.',
            'name.string' => 'The role name must be a string.',
            'name.max' => 'The role name cannot exceed 100 characters.',
            'slug.string' => 'The role slug must be a string.',
            'slug.max' => 'The role slug cannot exceed 100 characters.',
            'status.boolean' => 'The status field must be true or false.',
            'is_default.boolean' => 'The is_default field must be true or false.',
        ];
    }

    /**
     * Get normalized role data for creation.
     *
     * @return array{name: string, slug: string, status: bool, is_default: bool}
     */
    public function validatedRoleData(): array
    {
        $name = trim((string) $this->input('name'));
        $rawSlug = $this->input('slug');
        $slug = blank($rawSlug) ? Str::slug($name) : Str::slug((string) $rawSlug);

        return [
            'name' => $name,
            'slug' => strtolower($slug),
            'status' => $this->boolean('status', true),
            'is_default' => $this->boolean('is_default', false),
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
