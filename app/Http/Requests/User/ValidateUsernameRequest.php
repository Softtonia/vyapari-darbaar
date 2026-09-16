<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateUsernameRequest extends FormRequest
{
    /**
     * Reserved system usernames that cannot be claimed.
     *
     * @var list<string>
     */
    public const RESERVED_USERNAMES = [
        'admin',
        'administrator',
        'superadmin',
        'super.admin',
        'root',
        'system',
        'support',
        'help',
        'api',
        'null',
        'undefined',
        'anonymous',
        'guest',
        'dashboard',
        'auth',
        'login',
        'register',
        'vyaparidarbaar',
        'vyapari',
    ];

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
        $userId = $this->user()?->id ?? $this->input('ignore_user_id');

        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9]([a-zA-Z0-9._-]*[a-zA-Z0-9])?$/',
                'not_regex:/\.{2,}|_{2,}|-{2,}/',
                Rule::notIn(self::RESERVED_USERNAMES),
                Rule::unique('users', 'username')->ignore($userId),
            ],
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
            'username.required' => 'Please enter a username.',
            'username.string' => 'Username must be a valid text string.',
            'username.min' => 'Username must be at least :min characters long.',
            'username.max' => 'Username cannot be longer than :max characters.',
            'username.regex' => 'Username can only contain letters, numbers, dots, underscores, and hyphens (and cannot start or end with a special character).',
            'username.not_regex' => 'Username cannot contain consecutive special characters (like .., __, or --).',
            'username.not_in' => 'This username is reserved. Please choose a different one.',
            'username.unique' => 'This username is already taken. Please try another one.',
        ];
    }
}
