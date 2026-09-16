<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExchangeCommodityMapping
 */
class ExchangeCommodityMappingListResource extends JsonResource
{
    /**
     * Transform the resource into an array for list view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exchange_id' => $this->exchange_id,
            'exchange' => $this->whenLoaded('exchange', function () {
                if (! $this->exchange) {
                    return null;
                }

                return [
                    'id' => $this->exchange->id,
                    'name' => $this->exchange->name,
                    'code' => $this->exchange->code,
                ];
            }),
            'commodity_id' => $this->commodity_id,
            'commodity' => $this->whenLoaded('commodity', function () {
                if (! $this->commodity) {
                    return null;
                }

                return [
                    'id' => $this->commodity->id,
                    'name' => $this->commodity->name,
                    'code' => $this->commodity->code,
                    'unit' => $this->commodity->unit,
                ];
            }),
            'external_symbol' => $this->external_symbol,
            'external_code' => $this->external_code,
            'external_name' => $this->external_name,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
