<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserKycDocument extends Model
{
    protected $fillable = [
        'user_id',
        'document_type',
        'file_path',
        'status',
        'rejection_reason',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
