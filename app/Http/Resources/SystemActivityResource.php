<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserActivity
 */
class SystemActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array matching the System Activity Logs table.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        $userName = 'System';
        $userRole = 'Automated';
        $userEmail = null;
        $userId = null;

        if ($user) {
            $userId = $user->id;
            $userName = $user->name ?: ($user->username ?: 'Admin');
            $userRole = $user->roles->first()?->name ?? 'User';
            $userEmail = $user->email;
        }

        return [
            'id' => $this->id,
            'date_time' => $this->created_at?->format('d M Y, h:i A'),
            'created_at' => $this->created_at?->toISOString(),
            'user' => [
                'id' => $userId,
                'name' => $userName,
                'role' => ucwords(str_replace(['_', '-'], ' ', (string) $userRole)),
                'email' => $userEmail,
            ],
            'action' => $this->action ?? 'Updated',
            'module' => $this->module ?? 'Website',
            'description' => $this->description,
            'ip_address' => $this->ip_address ?: '-',
            'status' => $this->status ?? 'Success',
            'properties' => $this->properties ?? [],
        ];
    }
}
