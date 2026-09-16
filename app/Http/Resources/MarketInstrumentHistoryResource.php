<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketInstrumentHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $instrument = $this->resource['instrument'];
        $commodity = $instrument->mapping?->commodity;

        return [
            'instrument' => [
                'id' => $instrument->id,
                'external_instrument_id' => $instrument->external_instrument_id,
                'symbol' => $instrument->symbol,
                'instrument_name' => $instrument->instrument_name,
                'instrument_type' => $instrument->instrument_type instanceof \App\Enums\InstrumentType
                    ? $instrument->instrument_type->value
                    : (string) $instrument->instrument_type,
                'original_expiry_date' => $instrument->original_expiry_date instanceof \DateTimeInterface
                    ? $instrument->original_expiry_date->format('Y-m-d')
                    : (string) $instrument->original_expiry_date,
                'actual_expiry_date' => $instrument->actual_expiry_date instanceof \DateTimeInterface
                    ? $instrument->actual_expiry_date->format('Y-m-d')
                    : (string) $instrument->actual_expiry_date,
                'strike_price' => $instrument->strike_price !== null ? (string) $instrument->strike_price : null,
                'option_type' => $instrument->option_type instanceof \App\Enums\OptionType
                    ? $instrument->option_type->value
                    : ($instrument->option_type ? (string) $instrument->option_type : null),
                'lot_size' => $instrument->lot_size !== null ? (string) $instrument->lot_size : null,
                'tick_size' => $instrument->tick_size !== null ? (string) $instrument->tick_size : null,
                'quote_unit' => $instrument->quote_unit,
                'contract_unit' => $instrument->contract_unit,
                'lifecycle_status' => $instrument->lifecycle_status instanceof \App\Enums\InstrumentLifecycleStatus
                    ? $instrument->lifecycle_status->value
                    : (string) $instrument->lifecycle_status,
                'is_enabled' => (bool) $instrument->is_enabled,
                'exchange' => $instrument->exchange ? [
                    'id' => $instrument->exchange->id,
                    'code' => $instrument->exchange->code,
                    'name' => $instrument->exchange->name,
                    'slug' => $instrument->exchange->slug,
                ] : null,
                'commodity' => $commodity ? [
                    'id' => $commodity->id,
                    'name' => $commodity->name,
                    'code' => $commodity->code,
                    'slug' => $commodity->slug,
                ] : null,
            ],
            'interval' => $this->resource['interval'] ?? '1D',
            'from' => $this->resource['from'],
            'to' => $this->resource['to'],
            'items' => MarketBhavcopyResource::collection($this->resource['items']),
        ];
    }
}
