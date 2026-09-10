<?php

namespace App\Http\Requests\Admin\Notification;

use App\Enums\AudienceType;
use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('send_now')) {
            $this->merge(['send_now' => filter_var($this->input('send_now'), FILTER_VALIDATE_BOOLEAN)]);
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
            'notification_type' => ['required', 'string', Rule::enum(NotificationType::class)],
            'audience_type' => ['required', 'string', Rule::enum(AudienceType::class)],
            'user_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('audience_type') === AudienceType::SINGLE_USER->value),
                'exists:users,id',
            ],
            'user_ids' => [
                'nullable',
                'array',
                Rule::requiredIf(fn () => $this->input('audience_type') === AudienceType::SELECTED_USERS->value),
            ],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'topic_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('audience_type') === AudienceType::TOPIC->value),
                'exists:notification_topics,id',
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
            'image_url' => ['nullable', 'url', 'max:2048'],
            'click_url' => ['nullable', 'url', 'max:2048'],
            'data' => ['nullable', 'array'],
            'send_now' => ['nullable', 'boolean'],
            'scheduled_at' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $this->has('send_now') && ! $this->input('send_now')),
            ],
        ];
    }
}
