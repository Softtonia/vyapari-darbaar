<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExchangeInstrument
 */
class ExchangeInstrumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $commodity = $this->mapping?->commodity;

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
                    'slug' => $this->exchange->slug,
                ];
            }),
            'exchange_commodity_mapping_id' => $this->exchange_commodity_mapping_id,
            'mapping' => $this->whenLoaded('mapping', function () {
                if (! $this->mapping) {
                    return null;
                }

                return [
                    'id' => $this->mapping->id,
                    'external_symbol' => $this->mapping->external_symbol,
                    'external_code' => $this->mapping->external_code,
                    'external_name' => $this->mapping->external_name,
                ];
            }),
            'commodity' => $commodity ? [
                'id' => $commodity->id,
                'name' => $commodity->name,
                'code' => $commodity->code,
                'slug' => $commodity->slug,
                'unit' => $commodity->unit,
            ] : null,
            'external_instrument_id' => $this->external_instrument_id,
            'symbol' => $this->symbol,
            'instrument_name' => $this->instrument_name,
            'instrument_type' => $this->instrument_type instanceof \App\Enums\InstrumentType ? $this->instrument_type->value : (string) $this->instrument_type,
            'original_expiry_date' => $this->original_expiry_date ? $this->original_expiry_date->format('Y-m-d') : null,
            'actual_expiry_date' => $this->actual_expiry_date ? $this->actual_expiry_date->format('Y-m-d') : null,
            'contract_month' => $this->contract_month,
            'strike_price' => $this->strike_price !== null ? (string) $this->strike_price : null,
            'option_type' => $this->option_type instanceof \App\Enums\OptionType ? $this->option_type->value : ($this->option_type ? (string) $this->option_type : null),
            'lot_size' => $this->lot_size !== null ? (string) $this->lot_size : null,
            'tick_size' => $this->tick_size !== null ? (string) $this->tick_size : null,
            'quote_unit' => $this->quote_unit,
            'contract_unit' => $this->contract_unit,
            'lifecycle_status' => $this->lifecycle_status instanceof \App\Enums\InstrumentLifecycleStatus ? $this->lifecycle_status->value : (string) $this->lifecycle_status,
            'is_enabled' => (bool) $this->is_enabled,
            'listed_at' => $this->listed_at?->toISOString(),
            'delisted_at' => $this->delisted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
