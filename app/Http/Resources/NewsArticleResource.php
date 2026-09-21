<?php

namespace App\Http\Resources;

use App\Models\NewsArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NewsArticle
 */
class NewsArticleResource extends JsonResource
{
    /**
     * Transform the resource into a complete detailed array.
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
            'content' => $this->content,
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
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'source' => new NewsSourceResource($this->whenLoaded('source')),
            'category' => new NewsCategoryResource($this->whenLoaded('category')),
            'media' => NewsMediaResource::collection($this->whenLoaded('media')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
