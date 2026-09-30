<?php

namespace App\Http\Requests\Api\Admin\DataSource;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'website_name'              => ['sometimes', 'required', 'string', 'max:255'],
            'link'                      => ['sometimes', 'required', 'url', 'max:500'],
            'description'               => ['nullable', 'string'],
            'job_type'                  => ['sometimes', 'required', 'in:daily,weekly,monthly,quarterly'],
            'schedule_time'             => ['nullable', 'date_format:H:i'],
            'schedule_day_of_week'      => ['nullable', 'integer', 'between:0,6'],
            'schedule_day_of_month'     => ['nullable', 'integer', 'between:1,31'],
            'schedule_month_of_quarter' => ['nullable', 'integer', 'between:1,3'],
            'status'                    => ['sometimes', 'boolean'],
        ];
    }
}