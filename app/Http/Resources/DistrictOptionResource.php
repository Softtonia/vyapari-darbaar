<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\District
 */
class DistrictOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array for dropdown options.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state_id' => $this->state_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
        ];
    }
}
