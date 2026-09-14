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
        $merge = [];

        if ($this->has('send_now')) {
            $merge['send_now'] = filter_var($this->input('send_now'), FILTER_VALIDATE_BOOLEAN);
        }

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
                // If custom category type was sent, default notification_type to push_and_in_app
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
