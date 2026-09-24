<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hashid,
            'name' => $this->name,
            'email_template_id' => $this->whenLoaded('emailTemplate', fn () => $this->emailTemplate->hashid, $this->email_template_id),
            'email_template_name' => $this->whenLoaded('emailTemplate', fn () => $this->emailTemplate->name),
            'send_type' => $this->send_type->value,
            'send_type_label' => $this->send_type->label(),
            'event' => $this->event?->value,
            'event_label' => $this->event?->label(),
            'scheduled_at' => $this->scheduled_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
