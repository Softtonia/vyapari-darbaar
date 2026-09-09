<?php

namespace App\Http\Requests\Admin\Notification;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationRequest extends FormRequest
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
            'recipient_type' => ['required', 'string', 'in:single,all,role'],
            'user_id' => ['required_if:recipient_type,single', 'nullable', 'integer', 'exists:users,id'],
            'role' => ['required_if:recipient_type,role', 'nullable', 'string', 'in:user,trader,subscriber,advertiser'],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'action_url' => ['nullable', 'string', 'url', 'max:500'],
            'type' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
