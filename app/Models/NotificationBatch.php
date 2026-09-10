<?php

namespace App\Models;

use App\Enums\AudienceType;
use App\Enums\BatchStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationBatch extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notification_batches';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'parent_batch_id',
        'template_id',
        'topic_id',
        'title',
        'body',
        'image_url',
        'click_url',
        'data_json',
        'notification_type',
        'audience_type',
        'target_count',
        'processed_count',
        'success_count',
        'partial_count',
        'failed_count',
        'skipped_count',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'notification_type' => NotificationType::class,
            'audience_type' => AudienceType::class,
            'status' => BatchStatus::class,
            'target_count' => 'integer',
            'processed_count' => 'integer',
            'success_count' => 'integer',
            'partial_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Parent batch if this batch is a retry.
     */
    public function parentBatch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'parent_batch_id');
    }

    /**
     * Child retry batches.
     */
    public function retryBatches(): HasMany
    {
        return $this->hasMany(NotificationBatch::class, 'parent_batch_id');
    }

    /**
     * Template associated with this batch.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /**
     * Topic associated with this batch if audience_type is topic.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(NotificationTopic::class, 'topic_id');
    }

    /**
     * Creator admin.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Target user snapshot rows for single_user, selected_users, topic.
     */
    public function batchUsers(): HasMany
    {
        return $this->hasMany(NotificationBatchUser::class, 'notification_batch_id');
    }

    /**
     * Logs generated during batch execution.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'notification_batch_id');
    }

    /**
     * In-app notifications generated for this batch.
     */
    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class, 'notification_batch_id');
    }

    /**
     * Calculate progress percentage safely.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_count <= 0) {
            return $this->status === BatchStatus::COMPLETED ? 100.0 : 0.0;
        }

        return round(min(100.0, ($this->processed_count / $this->target_count) * 100), 2);
    }
}
