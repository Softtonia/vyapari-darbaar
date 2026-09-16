<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginUserRequest extends FormRequest
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
            'username' => ['required_without_all:email,phone_number,number', 'nullable', 'string'],
            'email' => ['required_without_all:username,phone_number,number', 'nullable', 'string'],
            'phone_number' => ['required_without_all:username,email,number', 'nullable', 'string'],
            'number' => ['required_without_all:username,email,phone_number', 'nullable', 'string'],
            'password' => ['required_without_all:otp,email_otp,number_otp', 'nullable', 'string'],
            'otp' => ['required_without_all:password,email_otp,number_otp', 'nullable', 'string', 'size:6'],
            'email_otp' => ['nullable', 'string', 'size:6'],
            'number_otp' => ['nullable', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
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
            'username.required_without_all' => 'Please enter your username, email, or phone number.',
            'email.required_without_all' => 'Please enter your username, email, or phone number.',
            'phone_number.required_without_all' => 'Please enter your username, email, or phone number.',
            'number.required_without_all' => 'Please enter your username, email, or phone number.',
            'password.required_without_all' => 'Please provide either a password or OTP to log in.',
            'otp.required_without_all' => 'Please provide either a password or OTP to log in.',
            'otp.size' => 'The OTP must be 6 digits.',
            'email_otp.size' => 'The email OTP must be 6 digits.',
            'number_otp.size' => 'The phone number OTP must be 6 digits.',
        ];
    }

    /**
     * Get validated login credentials.
     *
     * @return array{username: string, email?: string|null, phone_number?: string|null, password?: string|null, otp?: string|null, email_otp?: string|null, number_otp?: string|null}
     */
    public function credentials(): array
    {
        $identifier = $this->input('username')
            ?? $this->input('email')
            ?? $this->input('phone_number')
            ?? $this->input('number');

        $otp = $this->input('otp') ?? $this->input('email_otp') ?? $this->input('number_otp');

        return [
            'username' => trim((string) $identifier),
            'email' => $this->input('email') !== null ? strtolower(trim((string) $this->input('email'))) : null,
            'phone_number' => ($this->input('phone_number') ?? $this->input('number')) !== null ? trim((string) ($this->input('phone_number') ?? $this->input('number'))) : null,
            'password' => $this->input('password') !== null ? (string) $this->input('password') : null,
            'otp' => $otp !== null ? (string) $otp : null,
            'email_otp' => $this->input('email_otp') !== null ? (string) $this->input('email_otp') : null,
            'number_otp' => $this->input('number_otp') !== null ? (string) $this->input('number_otp') : null,
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
