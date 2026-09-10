<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'parent_batch_id' => $this->parent_batch_id,
            'title' => $this->title,
            'notification_type' => $this->notification_type?->value ?? $this->notification_type,
            'audience_type' => $this->audience_type?->value ?? $this->audience_type,
            'target_count' => (int) $this->target_count,
            'processed_count' => (int) $this->processed_count,
            'success_count' => (int) $this->success_count,
            'partial_count' => (int) $this->partial_count,
            'failed_count' => (int) $this->failed_count,
            'skipped_count' => (int) $this->skipped_count,
            'progress_percentage' => $this->progress_percentage,
            'status' => $this->status?->value ?? $this->status,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'template' => $this->template ? [
                'id' => $this->template->id,
                'name' => $this->template->name,
                'code' => $this->template->code,
            ] : null,
            'topic' => $this->topic ? [
                'id' => $this->topic->id,
                'name' => $this->topic->name,
                'slug' => $this->topic->slug,
            ] : null,
            'created_by' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
