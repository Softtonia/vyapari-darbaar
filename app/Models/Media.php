<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'attachment_id',
        'image_url',
    ];

    public function getImageUrlAttribute($value)
    {
        if (empty($value)) return $value;
        // If it's already an absolute URL (e.g. external image), return as is
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        return url($value);
    }

    protected $casts = [
        'attachment_id' => 'integer',
    ];
    public function category()
    {
        return $this->hasOne(CommodityCategory::class, 'media_id');
    }

    public function subcategory()
    {
        return $this->hasOne(CommoditySubcategory::class, 'media_id');
    }
}
