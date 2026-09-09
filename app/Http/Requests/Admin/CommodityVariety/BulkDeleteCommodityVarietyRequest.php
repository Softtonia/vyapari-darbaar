<?php

namespace App\Http\Requests\Admin\CommodityVariety;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDeleteCommodityVarietyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('commodity_varieties', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
