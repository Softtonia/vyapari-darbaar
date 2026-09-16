<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExchangeCommodityMapping
 */
class PublicExchangeCommodityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $commodity = $this->commodity;

        return [
            'id' => $commodity?->id,
            'name' => $commodity?->name,
            'code' => $commodity?->code,
            'unit' => $commodity?->unit,
            'exchange_product' => [
                'mapping_id' => $this->id,
                'symbol' => $this->external_symbol,
                'name' => $this->external_name ?? $commodity?->name,
            ],
        ];
    }
}
