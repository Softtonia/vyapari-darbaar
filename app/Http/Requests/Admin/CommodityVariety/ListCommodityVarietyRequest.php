<?php

namespace App\Http\Requests\Admin\CommodityVariety;

use App\Models\Commodity;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCommodityVarietyRequest extends FormRequest
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

        if ($this->has('commodity_id')) {
            $commId = $this->query('commodity_id');
            $sanitized['commodity_id'] = ($commId !== null && $commId !== '') ? (int) $commId : null;
        }

        if ($this->has('commodity_subcategory_id')) {
            $subcatId = $this->query('commodity_subcategory_id');
            $sanitized['commodity_subcategory_id'] = ($subcatId !== null && $subcatId !== '') ? (int) $subcatId : null;
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
            'commodity_id' => [
                'nullable',
                'integer',
                Rule::exists('commodities', 'id')->whereNull('deleted_at'),
            ],
            'commodity_subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('commodity_subcategories', 'id')->whereNull('deleted_at'),
            ],
            'commodity_category_id' => [
                'nullable',
                'integer',
                Rule::exists('commodity_categories', 'id')->whereNull('deleted_at'),
            ],
            'status' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', Rule::in(CommodityVariety::ALLOWED_SORT_COLUMNS)],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $commodityId = $this->query('commodity_id');
            $subcategoryId = $this->query('commodity_subcategory_id');
            $categoryId = $this->query('commodity_category_id');

            // 1. Commodity + Category consistency
            if ($commodityId && $categoryId && ! $validator->errors()->has('commodity_id') && ! $validator->errors()->has('commodity_category_id')) {
                $belongs = Commodity::query()
                    ->where('id', (int) $commodityId)
                    ->where('commodity_category_id', (int) $categoryId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add(
                        'commodity_id',
                        'The selected commodity does not belong to the selected commodity category.'
                    );
                }
            }

            // 2. Subcategory + Commodity consistency
            if ($subcategoryId && $commodityId && ! $validator->errors()->has('commodity_subcategory_id') && ! $validator->errors()->has('commodity_id')) {
                $belongs = CommoditySubcategory::query()
                    ->where('id', (int) $subcategoryId)
                    ->where('commodity_id', (int) $commodityId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add(
                        'commodity_subcategory_id',
                        'The selected commodity subcategory does not belong to the selected commodity.'
                    );
                }
            }

            // 3. Subcategory + Category consistency
            if ($subcategoryId && $categoryId && ! $validator->errors()->has('commodity_subcategory_id') && ! $validator->errors()->has('commodity_category_id')) {
                $belongs = CommoditySubcategory::query()
                    ->where('id', (int) $subcategoryId)
                    ->whereNull('deleted_at')
                    ->whereHas('commodity', function ($query) use ($categoryId) {
                        $query->where('commodity_category_id', (int) $categoryId)->whereNull('deleted_at');
                    })
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add(
                        'commodity_subcategory_id',
                        'The selected commodity subcategory does not belong to the selected commodity category.'
                    );
                }
            }
        });
    }
}
