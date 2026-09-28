<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyBankDetail extends Model
{
    protected $fillable = [
        'company_id',
        'account_holder_name',
        'bank_name',
        'account_number',
        'ifsc_code',
        'branch_name',
        'is_primary',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
