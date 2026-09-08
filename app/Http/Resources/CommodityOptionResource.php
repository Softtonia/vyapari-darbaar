<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Commodity
 */
class CommodityOptionResource extends JsonResource
{
    /**
     * Transform the resource into a lightweight array for dropdown selections.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commodity_category_id' => $this->commodity_category_id,
            'name_en' => $this->name_en,
            'name_hi' => $this->name_hi,
            'slug' => $this->slug,
        ];
    }
}
