<?php

namespace App\Models;

use App\Enums\CampaignEvent;
use App\Enums\CampaignSendType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email_template_id',
        'send_type',
        'event',
        'target_users',
        'scheduled_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'send_type' => CampaignSendType::class,
            'event' => CampaignEvent::class,
            'target_users' => 'array',
            'scheduled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }
}
