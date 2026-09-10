<?php

namespace App\Http\Requests\Admin\Notification;

use App\Enums\TemplateChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:100', 'unique:notification_templates,code'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'click_url' => ['nullable', 'url', 'max:2048'],
            'data_json' => ['nullable', 'array'],
            'channel' => ['required', 'string', Rule::enum(TemplateChannel::class)],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
