<?php

namespace App\Http\Resources;

use App\Services\Firebase\NotificationDeviceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminNotificationDeviceResource extends JsonResource
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
            'device_type' => $this->device_type,
            'device_name' => $this->device_name,
            'browser' => $this->browser,
            'masked_token' => NotificationDeviceService::maskToken($this->fcm_token),
            'ip_address' => $this->ip_address,
            'is_active' => (bool) $this->is_active,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
