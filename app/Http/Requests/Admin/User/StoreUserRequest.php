<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreUserRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['sometimes', 'nullable', 'string', 'exists:roles,name'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'commodities_handled' => ['nullable'],
            'trade_preference' => ['nullable', 'string', 'in:buy,sell,both,BUY,SELL,BOTH'],
            'buy_sell_preference' => ['nullable', 'string', 'in:buy,sell,both,BUY,SELL,BOTH'],
            'verification_status' => ['nullable', 'string', 'in:pending,verified,rejected'],
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
            'first_name.required' => 'The first name is required.',
            'last_name.required' => 'The last name is required.',
            'phone_number.required' => 'The phone number is required.',
            'phone_number.regex' => 'The phone number format is invalid.',
            'email.required' => 'The email address is required.',
            'email.unique' => 'The email has already been taken.',
            'role.exists' => 'The selected role is invalid.',
        ];
    }

    /**
     * Get validated data with normalized fields.
     *
     * @return array<string, mixed>
     */
    public function validatedUserData(): array
    {
        $firstName = trim((string) $this->input('first_name'));
        $lastName = trim((string) $this->input('last_name'));
        $phone = trim((string) $this->input('phone_number'));
        $email = strtolower(trim((string) $this->input('email')));
        $role = $this->filled('role') ? trim((string) $this->input('role')) : 'user';

        $data = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim("{$firstName} {$lastName}"),
            'phone_number' => $phone,
            'email' => $email,
            'role' => $role,
        ];

        foreach (['company_name', 'contact_person', 'business_type', 'gstin', 'country', 'state', 'city', 'address', 'commodities_handled', 'trade_preference', 'buy_sell_preference', 'verification_status'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->input($field);
            }
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
