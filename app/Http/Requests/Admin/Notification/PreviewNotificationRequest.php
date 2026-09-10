<?php

namespace App\Http\Requests\Admin\Notification;

use App\Enums\AudienceType;
use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewNotificationRequest extends FormRequest
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
            'notification_type' => ['nullable', 'string', Rule::enum(NotificationType::class)],
            'audience_type' => ['required', 'string', Rule::enum(AudienceType::class)],
            'user_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('audience_type') === AudienceType::SINGLE_USER->value),
            ],
            'user_ids' => [
                'nullable',
                'array',
                Rule::requiredIf(fn () => $this->input('audience_type') === AudienceType::SELECTED_USERS->value),
            ],
            'user_ids.*' => ['integer'],
            'topic_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('audience_type') === AudienceType::TOPIC->value),
            ],
            'template_id' => ['nullable', 'integer', 'exists:notification_templates,id'],
            'title' => [
                'nullable',
                'string',
                'max:200',
                Rule::requiredIf(fn () => empty($this->input('template_id'))),
            ],
            'body' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => empty($this->input('template_id'))),
            ],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'click_url' => ['nullable', 'string', 'max:2048'],
            'data' => ['nullable', 'array'],
        ];
    }
}
