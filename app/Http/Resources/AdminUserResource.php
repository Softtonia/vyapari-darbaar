<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class AdminUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleName = $this->roles->first()?->name ?? 'user';
        $isTrader = $roleName === 'trader' || ($this->relationLoaded('roles') ? $this->roles->contains('name', 'trader') : $this->hasRole('trader'));

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'phone_number' => $this->phone_number,
            'username' => $this->username,
            'email' => $this->email,
            'status' => $this->status,
            'must_change_password' => (bool) $this->must_change_password,
            'role' => $roleName,
            'company' => $this->when($isTrader, function () {
                $company = $this->relationLoaded('companies')
                    ? ($this->companies->firstWhere('pivot.is_primary', true) ?? $this->companies->first())
                    : $this->company;

                return $company ? new CompanyResource($company) : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'creator' => $this->whenLoaded('creator', function () {
                if (! $this->creator) {
                    return null;
                }

                return [
                    'id' => $this->creator->id,
                    'first_name' => $this->creator->first_name,
                    'last_name' => $this->creator->last_name,
                    'full_name' => $this->creator->full_name,
                ];
            }),
        ];
    }
}
