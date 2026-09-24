<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email_template_id' => $this->email_template_id,
            'send_type' => $this->send_type->value,
            'event' => $this->event?->value,
            'scheduled_at' => $this->scheduled_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'email_template' => new EmailTemplateResource($this->whenLoaded('emailTemplate')),
        ];
    }
}
