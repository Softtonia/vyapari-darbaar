<?php

namespace App\Http\Requests\Admin\Firebase;

use Illuminate\Foundation\Http\FormRequest;

class TestFirebaseNotificationRequest extends FormRequest
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
            'fcm_token' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:500'],
        ];
    }
}
