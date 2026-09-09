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
                    'name_en' => $this->commodity->name_en,
                    'name_hi' => $this->commodity->name_hi,
                    'slug' => $this->commodity->slug,
                    'category' => $this->commodity->relationLoaded('category') && $this->commodity->category ? [
                        'id' => $this->commodity->category->id,
                        'name_en' => $this->commodity->category->name_en,
                        'name_hi' => $this->commodity->category->name_hi,
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
                    'name_en' => $this->subcategory->name_en,
                    'name_hi' => $this->subcategory->name_hi,
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
                    'name_en' => $this->variety->name_en,
                    'name_hi' => $this->variety->name_hi,
                    'slug' => $this->variety->slug,
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
