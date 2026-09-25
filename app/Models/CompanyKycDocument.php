<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyKycDocument extends Model
{
    protected $table = 'kyc';

    protected $fillable = [
        'company_id',
        'user_id',
        'document_type',
        'file_path',
        'status',
        'upload_batch_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
