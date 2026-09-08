<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Commodity
 */
class CommodityResource extends JsonResource
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
