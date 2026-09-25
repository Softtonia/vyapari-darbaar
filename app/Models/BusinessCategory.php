<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessCategory extends Model
{
    protected $fillable = ['name', 'status'];

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_business_categories');
    }
}
