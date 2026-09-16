<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Exchange
 */
class ExchangeListResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'exchange_type' => $this->exchange_type instanceof \App\Enums\ExchangeType ? $this->exchange_type->value : (string) $this->exchange_type,
            'timezone' => $this->timezone,
            'website' => $this->website,
            'default_data_delay_minutes' => $this->default_data_delay_minutes,
            'sort_order' => (int) $this->sort_order,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
