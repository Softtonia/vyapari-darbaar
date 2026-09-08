<?php

namespace App\Http\Requests\Admin\Commodity;

use App\Models\Commodity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCommodityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare input for validation.
     */
    protected function prepareForValidation(): void
    {
        $sanitized = [];

        if ($this->has('search')) {
            $search = trim((string) $this->query('search'));
            $sanitized['search'] = $search !== '' ? $search : null;
        }

        if ($this->has('commodity_category_id')) {
            $catId = $this->query('commodity_category_id');
            $sanitized['commodity_category_id'] = ($catId !== null && $catId !== '') ? (int) $catId : null;
        }

        if ($this->has('sort_by')) {
            $sortBy = strtolower(trim((string) $this->query('sort_by')));
            $sanitized['sort_by'] = $sortBy !== '' ? $sortBy : null;
        }

        if ($this->has('sort_order')) {
            $sortOrder = strtolower(trim((string) $this->query('sort_order')));
            $sanitized['sort_order'] = $sortOrder !== '' ? $sortOrder : null;
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'commodity_category_id' => [
                'nullable',
                'integer',
                Rule::exists('commodity_categories', 'id')->whereNull('deleted_at'),
            ],
            'status' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', Rule::in(Commodity::ALLOWED_SORT_COLUMNS)],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
