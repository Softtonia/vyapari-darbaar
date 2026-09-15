<?php

namespace App\Http\Requests\Admin\Mandi;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteMandiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:mandis,id'],
        ];
    }
}
