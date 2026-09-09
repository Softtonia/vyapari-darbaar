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
        $emailRules = ['required', 'email', 'max:255'];

        if ($purpose === 'registration' || $purpose === 'register') {
            $emailRules[] = 'unique:users,email';
        }

        return [
            'email' => $emailRules,
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
        ];
    }
}
