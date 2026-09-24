<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\CampaignEvent;
use App\Enums\CampaignSendType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email_template_id' => ['required', 'integer', 'exists:email_templates,id'],
            'send_type' => ['required', 'string', Rule::enum(CampaignSendType::class)],
            'event' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $this->input('send_type') === CampaignSendType::TRIGGER->value),
                Rule::enum(CampaignEvent::class),
            ],
            'scheduled_at' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $this->input('send_type') === CampaignSendType::SCHEDULE->value),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
