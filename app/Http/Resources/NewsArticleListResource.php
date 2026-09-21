<?php

namespace App\Http\Resources;

use App\Models\NewsArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NewsArticle
 */
class NewsArticleListResource extends JsonResource
{
    /**
     * Transform the resource into a lightweight array for listings.
     * Excludes longtext content and internal database fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content_type' => $this->content_type instanceof \BackedEnum ? $this->content_type->value : (string) $this->content_type,
            'short_description' => $this->short_description,
            'featured_image' => $this->featured_image_url,
            'author_name' => $this->author_name,
            'source_url' => $this->source_url,
            'published_on' => $this->published_on?->format('Y-m-d'),
            'published_at' => $this->published_at?->toISOString(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status,
            'is_featured' => (bool) $this->is_featured,
            'is_breaking' => (bool) $this->is_breaking,
            'view_count' => (int) $this->view_count,
            'source' => $this->whenLoaded('source', function () {
                return [
                    'id' => $this->source->id,
                    'name' => $this->source->name,
                    'slug' => $this->source->slug,
                    'logo' => $this->source->logo_url,
                ];
            }),
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
