<?php

namespace App\Http\Requests\Admin\CommodityVariety;

use App\Models\Commodity;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCommodityVarietyRequest extends FormRequest
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
        $name = $this->has('name') ? trim((string) $this->input('name')) : null;
        $slug = $this->has('slug') && $this->input('slug') !== null ? trim((string) $this->input('slug')) : null;
        $desc = $this->has('description') ? trim((string) $this->input('description')) : null;
        $subcatId = $this->has('commodity_subcategory_id') && $this->input('commodity_subcategory_id') !== null && $this->input('commodity_subcategory_id') !== ''
            ? (int) $this->input('commodity_subcategory_id')
            : null;

        $normalizedSlug = null;
        if (! empty($slug)) {
            $normalizedSlug = Str::slug($slug);
        }

        $this->merge([
            'name' => $name !== '' ? $name : null,
            'slug' => $normalizedSlug,
            'commodity_subcategory_id' => $subcatId,
            'description' => $desc !== '' ? $desc : null,
            'status' => $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true : true,
            'sort_order' => $this->has('sort_order') && $this->input('sort_order') !== null && $this->input('sort_order') !== '' ? (int) $this->input('sort_order') : 0,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'commodity_id' => [
                'required',
                'integer',
                Rule::exists('commodities', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', true),
            ],
            'commodity_subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('commodity_subcategories', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', true),
            ],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', 'boolean'],
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
            $commodityId = $this->input('commodity_id');
            $subcategoryId = $this->input('commodity_subcategory_id');

            // 1. Verify parent Commodity's parent Category is active & non-deleted
            if ($commodityId && ! $validator->errors()->has('commodity_id')) {
                $validParentHierarchy = Commodity::query()
                    ->whereKey($commodityId)
                    ->where('status', true)
                    ->whereNull('deleted_at')
                    ->whereHas('category', function ($query) {
                        $query->where('status', true)->whereNull('deleted_at');
                    })
                    ->exists();

                if (! $validParentHierarchy) {
                    $validator->errors()->add(
                        'commodity_id',
                        'The selected commodity or its parent category is inactive or deleted.'
                    );
                }
            }

            // 2. If subcategory supplied, verify it belongs to exact commodity_id and its commodity/category hierarchy is active
            if ($subcategoryId && $commodityId && ! $validator->errors()->has('commodity_subcategory_id')) {
                $subcatValid = CommoditySubcategory::query()
                    ->whereKey($subcategoryId)
                    ->where('commodity_id', $commodityId)
                    ->where('status', true)
                    ->whereNull('deleted_at')
                    ->whereHas('commodity', function ($query) {
                        $query->where('status', true)
                            ->whereNull('deleted_at')
                            ->whereHas('category', function ($q) {
                                $q->where('status', true)->whereNull('deleted_at');
                            });
                    })
                    ->exists();

                if (! $subcatValid) {
                    $validator->errors()->add(
                        'commodity_subcategory_id',
                        'The selected commodity subcategory is invalid, inactive, or does not belong to the selected commodity.'
                    );
                }
            }

            // 3. If slug is explicitly provided, verify uniqueness per commodity (including soft-deletes)
            $slug = $this->input('slug');
            if ($commodityId && $slug && ! $validator->errors()->has('slug')) {
                $slugExists = CommodityVariety::withTrashed()
                    ->where('commodity_id', $commodityId)
                    ->where('slug', $slug)
                    ->exists();

                if ($slugExists) {
                    $validator->errors()->add('slug', 'The slug has already been taken for this commodity.');
                }
            }
        });
    }
}
