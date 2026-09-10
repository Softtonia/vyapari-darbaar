<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTopic extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notification_topics';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
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
            'status' => 'boolean',
        ];
    }

    /**
     * Get the administrator that created this topic.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get users subscribed to this topic.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_topic_users', 'notification_topic_id', 'user_id')
            ->withPivot('created_at');
    }

    /**
     * Topic user membership pivot records.
     */
    public function topicUsers(): HasMany
    {
        return $this->hasMany(NotificationTopicUser::class, 'notification_topic_id');
    }

    /**
     * Batches sent to this topic.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(NotificationBatch::class, 'topic_id');
    }
}
