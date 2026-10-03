<?php

namespace App\Http\Resources;

use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessProfile
 */
class BusinessProfileResource extends JsonResource
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
            'user_id' => $this->user_id,
            'company_name' => $this->company_name,
            'contact_person' => $this->contact_person,
            'business_type' => $this->business_type,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
            'address' => $this->address,
            'business_description' => $this->business_description,
            'business_commodities' => is_array($this->business_commodities) ? $this->business_commodities : [],
            'trade_preference' => $this->trade_preference,
            'verification_status' => $this->verification_status,
            'user' => new UserProfileResource($this->whenLoaded('user')),
            'kyc_documents' => $this->whenLoaded('kycDocuments'), // Assuming relationships still exist or fetched separately
            'business_documents' => $this->whenLoaded('businessDocuments'),
            'bank_details' => $this->whenLoaded('bankDetails'),
            'business_categories' => $this->whenLoaded('businessCategories'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
