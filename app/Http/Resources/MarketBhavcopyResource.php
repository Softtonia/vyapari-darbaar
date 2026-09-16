<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MarketBhavcopy
 */
class MarketBhavcopyResource extends JsonResource
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
            'exchange_instrument_id' => $this->exchange_instrument_id,
            'trade_date' => $this->trade_date instanceof \DateTimeInterface ? $this->trade_date->format('Y-m-d') : (string) $this->trade_date,
            'open_price' => $this->open_price !== null ? (string) $this->open_price : null,
            'high_price' => $this->high_price !== null ? (string) $this->high_price : null,
            'low_price' => $this->low_price !== null ? (string) $this->low_price : null,
            'close_price' => $this->close_price !== null ? (string) $this->close_price : null,
            'last_price' => $this->last_price !== null ? (string) $this->last_price : null,
            'previous_close_price' => $this->previous_close_price !== null ? (string) $this->previous_close_price : null,
            'settlement_price' => $this->settlement_price !== null ? (string) $this->settlement_price : null,
            'volume' => $this->volume !== null ? (string) $this->volume : null,
            'traded_value' => $this->traded_value !== null ? (string) $this->traded_value : null,
            'number_of_trades' => $this->number_of_trades,
            'open_interest' => $this->open_interest !== null ? (string) $this->open_interest : null,
            'change_in_open_interest' => $this->change_in_open_interest !== null ? (string) $this->change_in_open_interest : null,
            'source_timestamp' => $this->source_timestamp?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
        ];
    }
}
