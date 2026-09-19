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
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required_without_all:email,phone_number,number,mobile,phone,identifier', 'nullable', 'string'],
            'email' => ['required_without_all:username,phone_number,number,mobile,phone,identifier', 'nullable', 'string'],
            'phone_number' => ['required_without_all:username,email,number,mobile,phone,identifier', 'nullable', 'string'],
            'number' => ['required_without_all:username,email,phone_number,mobile,phone,identifier', 'nullable', 'string'],
            'mobile' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'identifier' => ['nullable', 'string'],
            'password' => ['required_without_all:otp,email_otp,number_otp,mobile_otp,code', 'nullable', 'string'],
            'otp' => ['required_without_all:password,email_otp,number_otp,mobile_otp,code', 'nullable', 'string', 'size:6'],
            'email_otp' => ['nullable', 'string', 'size:6'],
            'number_otp' => ['nullable', 'string', 'size:6'],
            'mobile_otp' => ['nullable', 'string', 'size:6'],
            'code' => ['nullable', 'string', 'size:6'],
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
            'username.required_without_all' => 'Please enter your username, email, or mobile number.',
            'email.required_without_all' => 'Please enter your username, email, or mobile number.',
            'phone_number.required_without_all' => 'Please enter your username, email, or mobile number.',
            'number.required_without_all' => 'Please enter your username, email, or mobile number.',
            'password.required_without_all' => 'Please provide either a password or OTP to log in.',
            'otp.required_without_all' => 'Please provide either a password or OTP to log in.',
            'otp.size' => 'The OTP must be 6 digits.',
            'email_otp.size' => 'The email OTP must be 6 digits.',
            'number_otp.size' => 'The mobile OTP must be 6 digits.',
            'mobile_otp.size' => 'The mobile OTP must be 6 digits.',
            'code.size' => 'The verification code must be 6 digits.',
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
            ?? $this->input('mobile')
            ?? $this->input('phone')
            ?? $this->input('number')
            ?? $this->input('identifier');

        $otp = $this->input('otp')
            ?? $this->input('email_otp')
            ?? $this->input('number_otp')
            ?? $this->input('mobile_otp')
            ?? $this->input('code');

        $phone = $this->input('phone_number')
            ?? $this->input('mobile')
            ?? $this->input('phone')
            ?? $this->input('number');

        return [
            'username' => trim((string) $identifier),
            'email' => $this->input('email') !== null ? strtolower(trim((string) $this->input('email'))) : null,
            'phone_number' => $phone !== null ? trim((string) $phone) : null,
            'password' => $this->input('password') !== null ? (string) $this->input('password') : null,
            'otp' => $otp !== null ? (string) $otp : null,
            'email_otp' => $this->input('email_otp') !== null ? (string) $this->input('email_otp') : null,
            'number_otp' => ($this->input('number_otp') ?? $this->input('mobile_otp')) !== null ? (string) ($this->input('number_otp') ?? $this->input('mobile_otp')) : null,
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
