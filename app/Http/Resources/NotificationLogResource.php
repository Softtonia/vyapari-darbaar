<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationLogResource extends JsonResource
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
            'batch_id' => $this->notification_batch_id,
            'batch' => $this->batch ? [
                'id' => $this->batch->id,
                'uuid' => $this->batch->uuid,
                'title' => $this->batch->title,
            ] : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'username' => $this->user->username,
            ] : null,
            'device' => $this->device ? [
                'id' => $this->device->id,
                'device_type' => $this->device->device_type,
                'device_name' => $this->device->device_name,
                'browser' => $this->device->browser,
                'ip_address' => $this->device->ip_address,
            ] : null,
            'channel' => $this->channel,
            'title' => $this->title,
            'body' => $this->body,
            'data_json' => $this->data_json,
            'status' => $this->status?->value ?? $this->status,
            'provider_message_id' => $this->provider_message_id,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
