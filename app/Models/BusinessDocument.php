<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessDocument extends Model
{
    protected $fillable = ['company_id', 'document_type', 'file_path', 'status'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
