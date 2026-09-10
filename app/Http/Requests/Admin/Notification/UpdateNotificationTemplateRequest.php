<?php

namespace App\Http\Requests\Admin\Notification;

use App\Enums\TemplateChannel;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationTemplateRequest extends FormRequest
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
        $template = $this->route('template');
        $templateId = $template instanceof NotificationTemplate ? $template->id : (int) $template;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:100', Rule::unique('notification_templates', 'code')->ignore($templateId)],
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
