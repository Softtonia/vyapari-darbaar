<?php

namespace App\Http\Requests\Admin;

use App\Actions\User\LoginUserAction;
use App\Models\Admin;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendAdminOtpRequest extends FormRequest
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
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required_without_all:username,phone_number,mobile,phone,number,identifier', 'nullable', 'string', 'max:255'],
            'username' => ['required_without_all:email,phone_number,mobile,phone,number,identifier', 'nullable', 'string', 'max:100'],
            'phone_number' => ['required_without_all:email,username,mobile,phone,number,identifier', 'nullable', 'string', 'max:25'],
            'mobile' => ['nullable', 'string', 'max:25'],
            'phone' => ['nullable', 'string', 'max:25'],
            'number' => ['nullable', 'string', 'max:25'],
            'identifier' => ['nullable', 'string', 'max:255'],
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
            'email.required_without_all' => 'Please provide an administrator email, username, or mobile number to receive an OTP.',
            'username.required_without_all' => 'Please provide an administrator email, username, or mobile number to receive an OTP.',
            'phone_number.required_without_all' => 'Please provide an administrator email, username, or mobile number to receive an OTP.',
        ];
    }

    /**
     * Resolve the targeted Administrator model.
     */
    public function targetAdmin(): ?Admin
    {
        $identifier = trim((string) (
            $this->input('identifier')
            ?? $this->input('email')
            ?? $this->input('username')
            ?? $this->input('phone_number')
            ?? $this->input('mobile')
            ?? $this->input('phone')
        ));

        if (empty($identifier)) {
            return null;
        }

        $phoneVariations = LoginUserAction::getPhoneVariations($identifier);

        return Admin::query()
            ->where(function ($query) use ($identifier, $phoneVariations) {
                $query->where('email', strtolower($identifier))
                    ->orWhere('username', $identifier)
                    ->orWhereIn('phone_number', $phoneVariations);
            })
            ->first();
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
