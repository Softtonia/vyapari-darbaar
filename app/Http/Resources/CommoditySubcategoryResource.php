<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CommoditySubcategory
 */
class CommoditySubcategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array for detail view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commodity_id' => $this->commodity_id,
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
            'name_en' => $this->name_en,
            'name_hi' => $this->name_hi,
            'slug' => $this->slug,
            'description_en' => $this->description_en,
            'description_hi' => $this->description_hi,
            'sort_order' => (int) $this->sort_order,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'creator' => $this->whenLoaded('creator', function () {
                if (! $this->creator) {
                    return null;
                }

                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'updater' => $this->whenLoaded('updater', function () {
                if (! $this->updater) {
                    return null;
                }

                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email,
                ];
            }),
        ];
    }
}
