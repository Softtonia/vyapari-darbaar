<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExchangeCommodityMapping
 */
class ExchangeCommodityMappingOptionResource extends JsonResource
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
            'exchange_id' => $this->exchange_id,
            'commodity_id' => $this->commodity_id,
            'external_symbol' => $this->external_symbol,
            'external_code' => $this->external_code,
            'external_name' => $this->external_name,
        ];
    }
}
