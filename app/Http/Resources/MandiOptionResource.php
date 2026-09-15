<?php

namespace App\Http\Resources;

use App\Enums\MarketType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Mandi
 */
class MandiOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array for dropdown options.
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
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'market_type' => $marketTypeEnum?->value ?? (string) $this->market_type,
        ];
    }
}
