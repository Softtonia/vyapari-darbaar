<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $rawIdentifier = $this->input('mobile')
            ?? $this->input('phone')
            ?? $this->input('phone_number')
            ?? $this->input('number')
            ?? $this->input('email')
            ?? $this->input('username')
            ?? $this->input('identifier');

        if ($rawIdentifier !== null && $rawIdentifier !== '') {
            $trimmed = trim((string) $rawIdentifier);
            if (str_contains($trimmed, '@')) {
                $this->merge(['email' => strtolower($trimmed)]);
            } elseif (preg_match('/^\+?[0-9]{7,15}$/', $trimmed)) {
                $this->merge([
                    'phone_number' => $trimmed,
                    'mobile' => $trimmed,
                ]);
            } else {
                $this->merge(['username' => $trimmed]);
            }
            $this->merge(['identifier' => $trimmed]);
        }

        if (! $this->has('purpose') || empty($this->input('purpose'))) {
            $routeName = (string) ($this->route()?->getName() ?? '');
            $path = (string) $this->path();

            if (str_contains($routeName, 'register') || str_contains($path, 'register')) {
                $this->merge(['purpose' => 'registration']);
            } elseif (str_contains($routeName, 'login') || str_contains($path, 'login')) {
                $this->merge(['purpose' => 'login']);
            } else {
                // Auto-detect based on user existence in database
                $email = $this->input('email');
                $phone = $this->input('phone_number') ?? $this->input('mobile');
                $username = $this->input('username');

                $exists = false;
                if ($email) {
                    $exists = \App\Models\User::where('email', $email)->exists();
                }
                if (! $exists && $phone) {
                    $variations = \App\Actions\User\LoginUserAction::getPhoneVariations($phone);
                    $exists = \App\Models\User::whereIn('phone_number', $variations)->exists();
                }
                if (! $exists && $username) {
                    $exists = \App\Models\User::where('username', $username)->exists();
                }

                $this->merge(['purpose' => $exists ? 'login' : 'registration']);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $purpose = (string) $this->input('purpose', 'registration');

        if ($purpose === 'registration' || $purpose === 'register') {
            return [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'purpose' => ['nullable', 'string', 'in:registration,register,login,verification,password_reset'],
            ];
        }

        return [
            'email' => ['required_without_all:username,phone_number,mobile,phone,number,identifier', 'nullable', 'string', 'max:255'],
            'username' => ['required_without_all:email,phone_number,mobile,phone,number,identifier', 'nullable', 'string', 'max:100'],
            'phone_number' => ['required_without_all:email,username,mobile,phone,number,identifier', 'nullable', 'string', 'max:25'],
            'mobile' => ['nullable', 'string', 'max:25'],
            'phone' => ['nullable', 'string', 'max:25'],
            'number' => ['nullable', 'string', 'max:25'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'in:registration,register,login,verification,password_reset'],
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
            'email.unique' => 'An account is already registered with this email address.',
            'email.required' => 'Please provide an email address.',
            'email.required_without_all' => 'Please provide your email, username, or phone number to receive an OTP.',
            'username.required_without_all' => 'Please provide your email, username, or phone number to receive an OTP.',
            'phone_number.required_without_all' => 'Please provide your email, username, or phone number to receive an OTP.',
        ];
    }
}
