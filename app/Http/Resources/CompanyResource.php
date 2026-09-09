<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
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
            'name' => $this->name,
            'company_name' => $this->name,
            'contact_person' => $this->contact_person,
            'business_type' => $this->business_type,
            'gstin' => $this->gstin,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'address' => $this->address,
            'commodities_handled' => is_array($this->commodities_handled) ? $this->commodities_handled : [],
            'trade_preference' => $this->trade_preference,
            'buy_sell_preference' => $this->trade_preference,
            'verification_status' => $this->verification_status,
            'pivot' => $this->whenPivotLoaded('user_has_companies', function () {
                return [
                    'role' => $this->pivot->role,
                    'is_primary' => (bool) $this->pivot->is_primary,
                ];
            }),
            'users' => UserProfileResource::collection($this->whenLoaded('users')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
