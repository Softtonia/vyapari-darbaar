<?php

namespace App\Http\Resources;

use App\Enums\NewsImportStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\NewsImportRun
 */
class NewsImportRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,

            'news_source_id'   => $this->news_source_id,
            'source'           => $this->whenLoaded('source', function () {
                if (! $this->source) {
                    return null;
                }

                return [
                    'id'   => $this->source->id,
                    'name' => $this->source->name,
                    'code' => $this->source->code,
                    'slug' => $this->source->slug,
                ];
            }),

            'news_category_id' => $this->news_category_id,
            'category'         => $this->whenLoaded('category', function () {
                if (! $this->category) {
                    return null;
                }

                return [
                    'id'   => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),

            'triggered_by'       => $this->triggered_by,
            'triggered_by_admin' => $this->whenLoaded('triggeredBy', function () {
                if (! $this->triggeredBy) {
                    return null;
                }

                return [
                    'id'    => $this->triggeredBy->id,
                    'name'  => $this->triggeredBy->name,
                    'email' => $this->triggeredBy->email,
                ];
            }),

            'feed_url'       => $this->feed_url,

            'status' => $this->status instanceof NewsImportStatus
                ? $this->status->value
                : (string) $this->status,
            'status_label' => $this->status instanceof NewsImportStatus
                ? $this->status->label()
                : (string) $this->status,

            'items_received' => $this->items_received,
            'items_imported' => $this->items_imported,
            'items_skipped'  => $this->items_skipped,
            'items_failed'   => $this->items_failed,

            'started_at'  => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),

            'error_summary' => $this->error_summary,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
