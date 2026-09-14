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
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // Support recipient_type / recipient alias for audience_type
        if (! $this->filled('audience_type')) {
            $recipientType = strtolower((string) ($this->input('recipient_type') ?? $this->input('recipient')));
            $audienceType = match ($recipientType) {
                'single', 'single_user', 'user' => AudienceType::SINGLE_USER->value,
                'selected', 'selected_users', 'multiple' => AudienceType::SELECTED_USERS->value,
                'all', 'all_users', 'broadcast' => AudienceType::ALL_USERS->value,
                'topic' => AudienceType::TOPIC->value,
                default => null,
            };
            if ($audienceType) {
                $merge['audience_type'] = $audienceType;
            }
        }

        // Support type / channel alias for notification_type
        if (! $this->filled('notification_type')) {
            $type = strtolower((string) ($this->input('type') ?? $this->input('channel')));
            if (in_array($type, NotificationType::values(), true)) {
                $merge['notification_type'] = $type;
            } elseif ($type !== '') {
                $merge['notification_type'] = NotificationType::PUSH_AND_IN_APP->value;
            }
        }

        // Support message alias for body
        if (! $this->filled('body') && $this->filled('message')) {
            $merge['body'] = (string) $this->input('message');
        }

        // Support action_url alias for click_url
        if (! $this->filled('click_url') && $this->filled('action_url')) {
            $merge['click_url'] = (string) $this->input('action_url');
        }

        if (! empty($merge)) {
            $this->merge($merge);
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
