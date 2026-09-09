<?php

namespace App\Http\Requests\Admin\CommodityVariety;

use App\Models\Commodity;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCommodityVarietyRequest extends FormRequest
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

        if ($this->has('commodity_id')) {
            $commId = $this->input('commodity_id');
            $sanitized['commodity_id'] = ($commId !== null && $commId !== '') ? (int) $commId : null;
        }

        if (array_key_exists('commodity_subcategory_id', $this->all())) {
            $subcatId = $this->input('commodity_subcategory_id');
            $sanitized['commodity_subcategory_id'] = ($subcatId !== null && $subcatId !== '') ? (int) $subcatId : null;
        }

        if ($this->has('name_en')) {
            $nameEn = trim((string) $this->input('name_en'));
            $sanitized['name_en'] = $nameEn !== '' ? $nameEn : null;
        }

        if ($this->has('name_hi')) {
            $nameHi = trim((string) $this->input('name_hi'));
            $sanitized['name_hi'] = $nameHi !== '' ? $nameHi : null;
        }

        if ($this->has('slug')) {
            $slug = trim((string) $this->input('slug'));
            $sanitized['slug'] = $slug !== '' ? Str::slug($slug) : null;
        }

        if ($this->has('description_en')) {
            $descEn = trim((string) $this->input('description_en'));
            $sanitized['description_en'] = $descEn !== '' ? $descEn : null;
        }

        if ($this->has('description_hi')) {
            $descHi = trim((string) $this->input('description_hi'));
            $sanitized['description_hi'] = $descHi !== '' ? $descHi : null;
        }

        if ($this->has('sort_order')) {
            $sortOrder = $this->input('sort_order');
            $sanitized['sort_order'] = ($sortOrder !== null && $sortOrder !== '') ? (int) $sortOrder : 0;
        }

        if ($this->has('status')) {
            $sanitized['status'] = filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $this->merge($sanitized);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $variety = $this->route('commodityVariety') ?? $this->route('commodity_variety');
        $currentCommodityId = $variety instanceof CommodityVariety ? $variety->commodity_id : null;
        $submittedCommodityId = $this->input('commodity_id');

        $commodityRule = ['sometimes', 'required', 'integer'];
        if ($submittedCommodityId && (int) $submittedCommodityId !== (int) $currentCommodityId) {
            $commodityRule[] = Rule::exists('commodities', 'id')
                ->whereNull('deleted_at')
                ->where('status', true);
        } else {
            $commodityRule[] = Rule::exists('commodities', 'id')
                ->whereNull('deleted_at');
        }

        return [
            'commodity_id' => $commodityRule,
            'commodity_subcategory_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('commodity_subcategories', 'id')->whereNull('deleted_at'),
            ],
            'name_en' => ['sometimes', 'required', 'string', 'max:150'],
            'name_hi' => ['nullable', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_hi' => ['nullable', 'string', 'max:2000'],
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
            $variety = $this->route('commodityVariety') ?? $this->route('commodity_variety');
            if (! ($variety instanceof CommodityVariety)) {
                $variety = CommodityVariety::find($variety);
            }

            if (! $variety) {
                return;
            }

            $submittedCommodityId = $this->input('commodity_id');
            $isChangingCommodity = $submittedCommodityId && (int) $submittedCommodityId !== (int) $variety->commodity_id;
            $targetCommodityId = $isChangingCommodity ? (int) $submittedCommodityId : (int) $variety->commodity_id;

            $hasExplicitSubcat = array_key_exists('commodity_subcategory_id', $this->all());
            $submittedSubcategoryId = $hasExplicitSubcat ? $this->input('commodity_subcategory_id') : null;

            // 1. If moving to another commodity
            if ($isChangingCommodity) {
                // Ensure target commodity and its parent category are active & non-deleted
                if (! $validator->errors()->has('commodity_id')) {
                    $validParent = Commodity::query()
                        ->whereKey($targetCommodityId)
                        ->where('status', true)
                        ->whereNull('deleted_at')
                        ->whereHas('category', function ($query) {
                            $query->where('status', true)->whereNull('deleted_at');
                        })
                        ->exists();

                    if (! $validParent) {
                        $validator->errors()->add(
                            'commodity_id',
                            'The target commodity or its parent category is inactive or deleted.'
                        );
                    }
                }

                // If currently assigned to a subcategory, request MUST explicitly decide subcategory relationship
                if ($variety->commodity_subcategory_id !== null && ! $hasExplicitSubcat) {
                    $validator->errors()->add(
                        'commodity_subcategory_id',
                        'When changing commodity, a valid commodity subcategory decision (or null) must be explicitly provided.'
                    );
                }

                // If explicit subcategory is provided (non-null), ensure it belongs to target commodity & is active/non-deleted
                if ($hasExplicitSubcat && $submittedSubcategoryId !== null && ! $validator->errors()->has('commodity_subcategory_id')) {
                    $subcatValid = CommoditySubcategory::query()
                        ->whereKey($submittedSubcategoryId)
                        ->where('commodity_id', $targetCommodityId)
                        ->where('status', true)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $subcatValid) {
                        $validator->errors()->add(
                            'commodity_subcategory_id',
                            'The selected commodity subcategory is invalid, inactive, or does not belong to the target commodity.'
                        );
                    }
                }
            } else {
                // Same commodity: if subcategory is changing to a new non-null value, validate it belongs to commodity & is active
                if ($hasExplicitSubcat && $submittedSubcategoryId !== null && (int) $submittedSubcategoryId !== (int) $variety->commodity_subcategory_id) {
                    $subcatValid = CommoditySubcategory::query()
                        ->whereKey($submittedSubcategoryId)
                        ->where('commodity_id', $targetCommodityId)
                        ->where('status', true)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $subcatValid) {
                        $validator->errors()->add(
                            'commodity_subcategory_id',
                            'The selected commodity subcategory is invalid, inactive, or does not belong to the commodity.'
                        );
                    }
                }
            }

            // 2. Determine effective slug: supplied slug if provided, else existing slug
            if ($this->has('slug') && $this->input('slug') !== null && $this->input('slug') !== '') {
                $effectiveSlug = Str::slug($this->input('slug'));
            } else {
                $effectiveSlug = $variety->slug;
            }

            // 3. Validate uniqueness of (targetCommodityId, effectiveSlug) across non-deleted & soft-deleted rows
            if ($effectiveSlug && ! $validator->errors()->has('slug')) {
                $slugExists = CommodityVariety::withTrashed()
                    ->where('commodity_id', $targetCommodityId)
                    ->where('slug', $effectiveSlug)
                    ->where('id', '!=', $variety->id)
                    ->exists();

                if ($slugExists) {
                    $validator->errors()->add('slug', 'The slug has already been taken for this commodity.');
                }
            }
        });
    }
}
