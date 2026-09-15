<?php

namespace App\Http\Resources;

use App\Enums\MarketType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Mandi
 */
class MandiResource extends JsonResource
{
    /**
     * Transform the resource into an array for detail view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $marketTypeEnum = $this->market_type instanceof MarketType
            ? $this->market_type
            : MarketType::tryFrom((string) $this->market_type);

        return [
            'id' => $this->id,
            'district_id' => $this->district_id,
            'district' => $this->whenLoaded('district', function () {
                if (! $this->district) {
                    return null;
                }

                return [
                    'id' => $this->district->id,
                    'name' => $this->district->name,
                    'slug' => $this->district->slug,
                ];
            }),
            'state' => $this->whenLoaded('district', function () {
                if (! $this->district || ! $this->district->relationLoaded('state') || ! $this->district->state) {
                    return null;
                }

                return [
                    'id' => $this->district->state->id,
                    'name' => $this->district->state->name,
                    'slug' => $this->district->state->slug,
                    'code' => $this->district->state->code,
                ];
            }),
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'market_type' => $marketTypeEnum?->value ?? (string) $this->market_type,
            'market_type_label' => $marketTypeEnum?->label() ?? (string) $this->market_type,
            'address' => $this->address,
            'pincode' => $this->pincode,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'website' => $this->website,
            'sort_order' => (int) $this->sort_order,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'creator' => $this->whenLoaded('creator', function () {
                if (! $this->creator) {
                    return null;
                }

                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'updater' => $this->whenLoaded('updater', function () {
                if (! $this->updater) {
                    return null;
                }

                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email,
                ];
            }),
        ];
    }
}
