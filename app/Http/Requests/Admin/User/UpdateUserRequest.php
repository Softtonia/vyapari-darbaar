<?php

namespace App\Http\Requests\Admin\User;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');
        $userId = $user?->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role' => ['sometimes', 'nullable', 'string', 'exists:roles,name'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var User|null $user */
            $user = $this->route('user');

            if ($this->has('username')) {
                $requestedUsername = (string) $this->input('username');
                if ($user && $requestedUsername !== $user->username) {
                    $validator->errors()->add(
                        'username',
                        'The username is immutable and cannot be modified.'
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
            'first_name.required' => 'The first name is required.',
            'last_name.required' => 'The last name is required.',
            'phone_number.regex' => 'The phone number format is invalid.',
            'email.required' => 'The email address is required.',
            'email.unique' => 'The email has already been taken.',
            'role.exists' => 'The selected role is invalid.',
        ];
    }

    /**
     * Get validated update data with normalized fields.
     *
     * @return array{first_name: string, last_name: string, name: string, email: string, phone_number?: string|null, role?: string|null}
     */
    public function validatedUserData(): array
    {
        $firstName = trim((string) $this->input('first_name'));
        $lastName = trim((string) $this->input('last_name'));
        $email = strtolower(trim((string) $this->input('email')));

        $data = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim("{$firstName} {$lastName}"),
            'email' => $email,
        ];

        if ($this->has('phone_number')) {
            $data['phone_number'] = $this->input('phone_number') !== null ? trim((string) $this->input('phone_number')) : null;
        }

        if ($this->filled('role')) {
            $data['role'] = trim((string) $this->input('role'));
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
