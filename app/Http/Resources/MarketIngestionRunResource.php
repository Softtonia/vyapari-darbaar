<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MarketIngestionRun
 */
class MarketIngestionRunResource extends JsonResource
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
            'exchange_id' => $this->exchange_id,
            'exchange' => $this->whenLoaded('exchange', function () {
                if (! $this->exchange) {
                    return null;
                }

                return [
                    'id' => $this->exchange->id,
                    'name' => $this->exchange->name,
                    'code' => $this->exchange->code,
                    'slug' => $this->exchange->slug,
                ];
            }),
            'source_type' => $this->source_type instanceof \App\Enums\IngestionSourceType
                ? $this->source_type->value
                : (string) $this->source_type,
            'source_type_label' => $this->source_type instanceof \App\Enums\IngestionSourceType
                ? $this->source_type->label()
                : (string) $this->source_type,
            'trade_date' => $this->trade_date instanceof \DateTimeInterface
                ? $this->trade_date->format('Y-m-d')
                : ($this->trade_date ? (string) $this->trade_date : null),
            'source_file_name' => $this->source_file_name,
            'source_checksum' => $this->source_checksum,
            'storage_path' => $this->storage_path,
            'status' => $this->status instanceof \App\Enums\IngestionStatus
                ? $this->status->value
                : (string) $this->status,
            'status_label' => $this->status instanceof \App\Enums\IngestionStatus
                ? $this->status->label()
                : (string) $this->status,
            'records_received' => $this->records_received,
            'records_inserted' => $this->records_inserted,
            'records_updated' => $this->records_updated,
            'records_skipped' => $this->records_skipped,
            'records_failed' => $this->records_failed,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'error_summary' => $this->error_summary,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
