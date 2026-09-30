<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_name',
        'link',
        'description',
        'job_type',
        'schedule_time',
        'schedule_day_of_week',
        'schedule_day_of_month',
        'schedule_month_of_quarter',
        'status',
    ];

    protected $casts = [
        'status'                   => 'boolean',
        'schedule_day_of_week'     => 'integer',
        'schedule_day_of_month'    => 'integer',
        'schedule_month_of_quarter'=> 'integer',
    ];
}