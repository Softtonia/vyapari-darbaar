<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'attachment_id',
        'image_url',
        'source_url',
    ];

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
