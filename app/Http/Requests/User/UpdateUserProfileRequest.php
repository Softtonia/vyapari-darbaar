<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
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
        $user = $this->user();
        $userId = $user?->id;

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'otp' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var User|null $user */
            $user = $this->user();

            if ($this->has('email')) {
                $newEmail = strtolower(trim((string) $this->input('email')));
                $currentEmail = strtolower(trim((string) $user?->email));

                if ($newEmail !== $currentEmail) {
                    $otp = trim((string) $this->input('otp'));

                    if ($otp === '') {
                        $validator->errors()->add(
                            'otp',
                            'The OTP is required when changing the email address.'
                        );
                    } else {
                        $otpService = app(OtpService::class);
                        if (! $otpService->verify($newEmail, $otp, 'user_email_update')) {
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
     * @return array{first_name?: string, last_name?: string, name?: string, phone_number?: string|null, email?: string}
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

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? ($this->user()?->first_name ?? '');
            $lastName = $data['last_name'] ?? ($this->user()?->last_name ?? '');
            $data['name'] = trim("{$firstName} {$lastName}");
        }

        if ($this->has('phone_number')) {
            $phone = trim((string) $this->input('phone_number'));
            $data['phone_number'] = $phone !== '' ? $phone : null;
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
