<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\District
 */
class DistrictListResource extends JsonResource
{
    /**
     * Transform the resource into an array for listing.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state_id' => $this->state_id,
            'state' => $this->whenLoaded('state', function () {
                if (! $this->state) {
                    return null;
                }

                return [
                    'id' => $this->state->id,
                    'name' => $this->state->name,
                    'slug' => $this->state->slug,
                    'code' => $this->state->code,
                ];
            }),
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'sort_order' => (int) $this->sort_order,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
