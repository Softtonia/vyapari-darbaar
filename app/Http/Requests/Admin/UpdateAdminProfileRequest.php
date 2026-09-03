<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdateAdminProfileRequest extends FormRequest
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
        /** @var Admin $admin */
        $admin = $this->user();
        $adminId = $admin?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($adminId),
            ],
            'current_password' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var Admin $admin */
            $admin = $this->user();

            if ($this->has('email')) {
                $newEmail = strtolower(trim((string) $this->input('email')));
                $currentEmail = strtolower(trim((string) $admin->email));

                if ($newEmail !== $currentEmail) {
                    $currentPassword = (string) $this->input('current_password');

                    if ($currentPassword === '') {
                        $validator->errors()->add(
                            'current_password',
                            'The current password is required when changing the email address.'
                        );
                    } elseif (! Hash::check($currentPassword, $admin->password)) {
                        $validator->errors()->add(
                            'current_password',
                            'The provided current password does not match our records.'
                        );
                    }
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
