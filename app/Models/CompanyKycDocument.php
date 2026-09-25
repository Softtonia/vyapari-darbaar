<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyKycDocument extends Model
{
    protected $table = 'kyc';

    protected $fillable = [
        'company_id',
        'document_type',
        'file_path',
        'status',
        'upload_batch_id',
    ];
}
