<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    use HasFactory;

    protected $table = 'site_settings';

    protected $fillable = [
        'site_name_en',
        'site_name_hi',
        'site_title_en',
        'site_title_hi',
        'site_description_en',
        'site_description_hi',
        'web_logo',
        'mobile_logo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Get the fully qualified public URL for web_logo.
     */
    public function getWebLogoUrlAttribute(): ?string
    {
        if (empty($this->web_logo)) {
            return null;
        }

        return Storage::disk('public')->url($this->web_logo);
    }

    /**
     * Get the fully qualified public URL for mobile_logo.
     */
    public function getMobileLogoUrlAttribute(): ?string
    {
        if (empty($this->mobile_logo)) {
            return null;
        }

        return Storage::disk('public')->url($this->mobile_logo);
    }
}
