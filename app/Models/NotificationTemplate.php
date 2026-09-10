<?php

namespace App\Models;

use App\Enums\TemplateChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notification_templates';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'title',
        'body',
        'image_url',
        'click_url',
        'data_json',
        'channel',
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
            'data_json' => 'array',
            'channel' => TemplateChannel::class,
            'status' => 'boolean',
        ];
    }

    /**
     * Get the administrator that created this template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get batches created with this template.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(NotificationBatch::class, 'template_id');
    }
}
