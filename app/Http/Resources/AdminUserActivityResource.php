<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserActivity
 */
class AdminUserActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array for Admin views.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                if (! $this->user) {
                    return null;
                }

                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'username' => $this->user->username,
                    'email' => $this->user->email,
                    'status' => $this->user->status,
                    'role' => $this->user->roles->first()?->name ?? 'user',
                ];
            }),
            'event' => $this->event,
            'description' => $this->description,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'properties' => $this->properties ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
