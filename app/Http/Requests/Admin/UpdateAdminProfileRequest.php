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
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($adminId),
            ],
            'otp' => ['nullable', 'string'],
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
                    $otp = trim((string) $this->input('otp'));

                    if ($otp === '') {
                        $validator->errors()->add(
                            'otp',
                            'The OTP is required when changing the email address.'
                        );
                    } else {
                        $otpService = app(\App\Services\OtpService::class);
                        if (! $otpService->verify($newEmail, $otp, 'admin_email_update')) {
                            $validator->errors()->add(
                                'otp',
                                'The OTP is invalid or has expired.'
                            );
                        }
                    }
                }
            }
        });
    }

    /**
     * Get normalized profile update data.
     *
     * @return array{first_name?: string, last_name?: string, name?: string, email?: string}
     */
    public function validatedProfileData(): array
    {
        $data = [];

        if ($this->has('first_name')) {
            $data['first_name'] = trim((string) $this->input('first_name'));
        }

        if ($this->has('last_name')) {
            $data['last_name'] = trim((string) $this->input('last_name'));
        }

        if ($this->has('name')) {
            $data['name'] = trim((string) $this->input('name'));
        }

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? ($this->user()?->first_name ?? '');
            $lastName = $data['last_name'] ?? ($this->user()?->last_name ?? '');
            $data['name'] = trim("{$firstName} {$lastName}");
        } elseif (isset($data['name']) && ! isset($data['first_name'])) {
            $parts = preg_split('/\s+/', $data['name'], 2);
            $data['first_name'] = ! empty($parts[0]) ? $parts[0] : 'Admin';
            $data['last_name'] = $parts[1] ?? '';
        }

        if ($this->has('email')) {
            $data['email'] = strtolower(trim((string) $this->input('email')));
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
