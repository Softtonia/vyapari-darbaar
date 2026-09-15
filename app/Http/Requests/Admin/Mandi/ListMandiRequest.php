<?php

namespace App\Http\Requests\Admin\Mandi;

use App\Enums\MarketType;
use App\Models\Mandi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMandiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'market_type' => ['nullable', 'string', Rule::enum(MarketType::class)],
            'pincode' => ['nullable', 'string', 'max:10'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:true,false,1,0'],
            'sort_by' => ['nullable', 'string', Rule::in(Mandi::ALLOWED_SORT_COLUMNS)],
            'sort_order' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
