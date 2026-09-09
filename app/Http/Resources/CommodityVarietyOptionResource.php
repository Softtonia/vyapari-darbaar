<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CommodityVariety
 */
class CommodityVarietyOptionResource extends JsonResource
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
            'commodity_id' => $this->commodity_id,
            'commodity_subcategory_id' => $this->commodity_subcategory_id,
            'name_en' => $this->name_en,
            'name_hi' => $this->name_hi,
            'slug' => $this->slug,
        ];
    }
}
