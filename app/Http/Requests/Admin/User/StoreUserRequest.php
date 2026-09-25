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
            'alternate_number' => ['nullable', 'string', 'max:25'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['sometimes', 'nullable', 'string', 'exists:roles,name'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            'address_line_2' => ['nullable', 'string', 'max:1000'],
            'pin_code' => ['nullable', 'string', 'max:20'],
            
            'pan_number' => ['nullable', 'string', 'max:50'],
            'year_of_establishment' => ['nullable', 'string', 'max:10'],
            'business_category' => ['nullable', 'string', 'max:100'],
            'no_of_employees' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            
            'bank_account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_ifsc_code' => ['nullable', 'string', 'max:20'],
            'bank_branch_name' => ['nullable', 'string', 'max:255'],
            
            'business_description' => ['nullable', 'string'],
            
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
            'alternate_number' => $this->input('alternate_number'),
            'date_of_birth' => $this->input('date_of_birth'),
            'gender' => $this->input('gender'),
            'profile_photo' => $this->hasFile('profile_photo') ? $this->file('profile_photo')->store('profiles', 'public') : null,
            'email' => $email,
            'role' => $role,
        ];

        $companyFields = [
            'company_name', 'contact_person', 'business_type', 'gstin', 'country_id', 'state_id', 'city_id', 'address',
            'address_line_2', 'pin_code', 'pan_number', 'year_of_establishment', 'business_category', 'no_of_employees',
            'website', 'bank_account_holder_name', 'bank_name', 'bank_account_number', 'bank_ifsc_code', 'bank_branch_name',
            'business_description', 'commodities_handled', 'trade_preference', 'buy_sell_preference', 'verification_status'
        ];

        foreach ($companyFields as $field) {
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
