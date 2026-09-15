<?php

namespace App\Http\Requests\Admin\District;

use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:true,false,1,0'],
            'sort_by' => ['nullable', 'string', Rule::in(District::ALLOWED_SORT_COLUMNS)],
            'sort_order' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
