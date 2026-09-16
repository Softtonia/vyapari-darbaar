<?php

namespace App\Http\Requests\User;

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $purpose = $this->input('purpose', 'registration');

        if ($purpose === 'registration' || $purpose === 'register') {
            return [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'purpose' => ['nullable', 'string', 'in:registration,register,login,verification,password_reset'],
            ];
        }

        return [
            'email' => ['required_without_all:username,phone_number', 'nullable', 'string', 'max:255'],
            'username' => ['required_without_all:email,phone_number', 'nullable', 'string', 'max:100'],
            'phone_number' => ['required_without_all:email,username', 'nullable', 'string', 'max:25'],
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
