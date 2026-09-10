<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminInAppNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'username' => $this->user->username,
            ] : null,
            'batch' => $this->batch ? [
                'id' => $this->batch->id,
                'uuid' => $this->batch->uuid,
                'title' => $this->batch->title,
            ] : null,
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'click_url' => $this->click_url,
            'data_json' => $this->data_json,
            'is_read' => ! is_null($this->read_at),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
