<?php

namespace App\Http\Requests\User\NotificationDevice;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationDeviceRequest extends FormRequest
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
            'device_type' => ['required', 'string', 'in:web,android,ios'],
            'device_name' => ['nullable', 'string', 'max:150'],
            'browser' => ['nullable', 'string', 'max:100'],
        ];
    }
}
