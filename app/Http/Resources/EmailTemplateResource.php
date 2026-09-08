<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EmailTemplate
 */
class EmailTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Services\EmailTemplateRenderer $renderer */
        $renderer = app(\App\Services\EmailTemplateRenderer::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'subject' => $this->subject,
            'body' => $this->body,
            'is_active' => (bool) $this->is_active,
            'supported_placeholders' => $renderer->getPlaceholdersWithMetadata($this->key),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
