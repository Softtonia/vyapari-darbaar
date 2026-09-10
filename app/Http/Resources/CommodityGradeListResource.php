<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CommodityGrade
 */
class CommodityGradeListResource extends JsonResource
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
            'commodity_id' => $this->commodity_id,
            'commodity_subcategory_id' => $this->commodity_subcategory_id,
            'commodity_variety_id' => $this->commodity_variety_id,
            'commodity' => $this->whenLoaded('commodity', function () {
                if (! $this->commodity) {
                    return null;
                }

                return [
                    'id' => $this->commodity->id,
                    'commodity_category_id' => $this->commodity->commodity_category_id,
                    'name' => $this->commodity->name,
                    'slug' => $this->commodity->slug,
                    'category' => $this->commodity->relationLoaded('category') && $this->commodity->category ? [
                        'id' => $this->commodity->category->id,
                        'name' => $this->commodity->category->name,
                        'slug' => $this->commodity->category->slug,
                    ] : null,
                ];
            }),
            'subcategory' => $this->whenLoaded('subcategory', function () {
                if (! $this->subcategory) {
                    return null;
                }

                return [
                    'id' => $this->subcategory->id,
                    'commodity_id' => $this->subcategory->commodity_id,
                    'name' => $this->subcategory->name,
                    'slug' => $this->subcategory->slug,
                ];
            }),
            'variety' => $this->whenLoaded('variety', function () {
                if (! $this->variety) {
                    return null;
                }

                return [
                    'id' => $this->variety->id,
                    'commodity_id' => $this->variety->commodity_id,
                    'commodity_subcategory_id' => $this->variety->commodity_subcategory_id,
                    'name' => $this->variety->name,
                    'slug' => $this->variety->slug,
                ];
            }),
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => (int) $this->sort_order,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
