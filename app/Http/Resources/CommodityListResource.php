<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Commodity
 */
class CommodityListResource extends JsonResource
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
            'commodity_category_id' => $this->commodity_category_id,
            'category' => $this->whenLoaded('category', function () {
                if (! $this->category) {
                    return null;
                }

                return [
                    'id' => $this->category->id,
                    'name_en' => $this->category->name_en,
                    'name_hi' => $this->category->name_hi,
                    'slug' => $this->category->slug,
                ];
            }),
            'name_en' => $this->name_en,
            'name_hi' => $this->name_hi,
            'slug' => $this->slug,
            'sort_order' => (int) $this->sort_order,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
