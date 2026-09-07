<?php

namespace App\Http\Requests\Admin\Role;

use App\Models\Role;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
        /** @var Role|string|int|null $roleParam */
        $roleParam = $this->route('role');
        $roleId = $roleParam instanceof Role ? $roleParam->id : $roleParam;

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('roles', 'slug')->ignore($roleId),
            ],
            'status' => ['nullable', 'boolean'],
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
            'slug.unique' => 'A role with this slug already exists.',
            'status.boolean' => 'The status field must be true or false.',
        ];
    }

    /**
     * Get validated data with normalized values.
     *
     * @param  Role  $currentRole
     * @return array{name: string, slug: string, status?: bool}
     */
    public function validatedRoleData(Role $currentRole): array
    {
        $name = trim((string) $this->input('name'));

        // If system role, preserve original system slug to protect internal invariants
        if ($currentRole->is_system) {
            $slug = $currentRole->slug;
        } elseif ($this->has('slug') && ! blank($this->input('slug'))) {
            $slug = strtolower(Str::slug((string) $this->input('slug')));
        } else {
            $slug = strtolower(Str::slug($name));
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
        ];

        if ($this->has('status')) {
            $data['status'] = $this->boolean('status');
        }

        return $data;
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
