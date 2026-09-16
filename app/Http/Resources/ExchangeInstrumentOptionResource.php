<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ExchangeInstrument
 */
class ExchangeInstrumentOptionResource extends JsonResource
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
            'exchange_commodity_mapping_id' => $this->exchange_commodity_mapping_id,
            'external_instrument_id' => $this->external_instrument_id,
            'symbol' => $this->symbol,
            'instrument_name' => $this->instrument_name,
            'instrument_type' => $this->instrument_type instanceof \App\Enums\InstrumentType ? $this->instrument_type->value : (string) $this->instrument_type,
            'actual_expiry_date' => $this->actual_expiry_date ? $this->actual_expiry_date->format('Y-m-d') : null,
            'contract_month' => $this->contract_month,
        ];
    }
}
