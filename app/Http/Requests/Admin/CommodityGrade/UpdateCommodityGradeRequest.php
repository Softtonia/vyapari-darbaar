<?php

namespace App\Http\Requests\Admin\CommodityGrade;

use App\Models\Commodity;
use App\Models\CommodityGrade;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCommodityGradeRequest extends FormRequest
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

        if (array_key_exists('commodity_variety_id', $this->all())) {
            $varietyId = $this->input('commodity_variety_id');
            $sanitized['commodity_variety_id'] = ($varietyId !== null && $varietyId !== '') ? (int) $varietyId : null;
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
        $grade = $this->route('commodityGrade') ?? $this->route('commodity_grade');
        $currentCommodityId = $grade instanceof CommodityGrade ? $grade->commodity_id : null;
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
            'commodity_variety_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('commodity_varieties', 'id')->whereNull('deleted_at'),
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
            $grade = $this->route('commodityGrade') ?? $this->route('commodity_grade');
            if (! ($grade instanceof CommodityGrade)) {
                $grade = CommodityGrade::find($grade);
            }

            if (! $grade) {
                return;
            }

            // Key presence flags
            $commProvided = array_key_exists('commodity_id', $this->all());
            $subcatProvided = array_key_exists('commodity_subcategory_id', $this->all());
            $varietyProvided = array_key_exists('commodity_variety_id', $this->all());

            // Effective values
            $effectiveCommodityId = $commProvided && $this->input('commodity_id') !== null && $this->input('commodity_id') !== ''
                ? (int) $this->input('commodity_id')
                : (int) $grade->commodity_id;

            $effectiveSubcategoryId = $subcatProvided
                ? ($this->input('commodity_subcategory_id') !== null && $this->input('commodity_subcategory_id') !== '' ? (int) $this->input('commodity_subcategory_id') : null)
                : $grade->commodity_subcategory_id;

            $effectiveVarietyId = $varietyProvided
                ? ($this->input('commodity_variety_id') !== null && $this->input('commodity_variety_id') !== '' ? (int) $this->input('commodity_variety_id') : null)
                : $grade->commodity_variety_id;

            // Did relationship actually change?
            $commodityChanged = ($effectiveCommodityId !== (int) $grade->commodity_id);
            $subcategoryChanged = ($effectiveSubcategoryId !== $grade->commodity_subcategory_id);
            $varietyChanged = ($effectiveVarietyId !== $grade->commodity_variety_id);

            // 1. Mandatory Commodity Change Rule: explicit subcat and variety decisions required
            if ($commodityChanged) {
                if (! $validator->errors()->has('commodity_id')) {
                    $validParent = Commodity::query()
                        ->whereKey($effectiveCommodityId)
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

                if (! $subcatProvided) {
                    $validator->errors()->add(
                        'commodity_subcategory_id',
                        'When changing commodity, a valid commodity subcategory decision (or null) must be explicitly provided.'
                    );
                }

                if (! $varietyProvided) {
                    $validator->errors()->add(
                        'commodity_variety_id',
                        'When changing commodity, a valid commodity variety decision (or null) must be explicitly provided.'
                    );
                }
            }

            // 2. Active checks for newly changed relationships
            if ($subcategoryChanged && $effectiveSubcategoryId !== null && ! $validator->errors()->has('commodity_subcategory_id')) {
                $subcatActive = CommoditySubcategory::query()
                    ->whereKey($effectiveSubcategoryId)
                    ->where('status', true)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $subcatActive) {
                    $validator->errors()->add(
                        'commodity_subcategory_id',
                        'The selected commodity subcategory is inactive or deleted.'
                    );
                }
            }

            if ($varietyChanged && $effectiveVarietyId !== null && ! $validator->errors()->has('commodity_variety_id')) {
                $varietyActive = CommodityVariety::query()
                    ->whereKey($effectiveVarietyId)
                    ->where('status', true)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $varietyActive) {
                    $validator->errors()->add(
                        'commodity_variety_id',
                        'The selected commodity variety is inactive or deleted.'
                    );
                }
            }

            // 3. Validate complete effective hierarchy tuple together
            if ($effectiveSubcategoryId !== null && ! $validator->errors()->has('commodity_subcategory_id')) {
                $subcatBelongs = CommoditySubcategory::query()
                    ->whereKey($effectiveSubcategoryId)
                    ->where('commodity_id', $effectiveCommodityId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $subcatBelongs) {
                    $validator->errors()->add(
                        'commodity_subcategory_id',
                        'The selected commodity subcategory does not belong to the selected commodity.'
                    );
                }
            }

            if ($effectiveVarietyId !== null && ! $validator->errors()->has('commodity_variety_id')) {
                $variety = CommodityVariety::query()
                    ->whereKey($effectiveVarietyId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($variety) {
                    if ((int) $variety->commodity_id !== (int) $effectiveCommodityId) {
                        $validator->errors()->add(
                            'commodity_variety_id',
                            'The selected commodity variety does not belong to the selected commodity.'
                        );
                    } else {
                        // Variety has subcategory
                        if ($variety->commodity_subcategory_id !== null) {
                            if ($effectiveSubcategoryId === null || (int) $effectiveSubcategoryId !== (int) $variety->commodity_subcategory_id) {
                                $validator->errors()->add(
                                    'commodity_subcategory_id',
                                    'The selected commodity variety belongs to a different subcategory.'
                                );
                            }
                        } else {
                            // Direct variety: effective subcategory MUST be null
                            if ($effectiveSubcategoryId !== null) {
                                $validator->errors()->add(
                                    'commodity_subcategory_id',
                                    'The selected commodity variety is a direct variety and cannot be assigned to a subcategory.'
                                );
                            }
                        }
                    }
                }
            }

            // 4. Determine effective slug and check uniqueness
            if ($this->has('slug') && $this->input('slug') !== null && $this->input('slug') !== '') {
                $effectiveSlug = Str::slug($this->input('slug'));
            } else {
                $effectiveSlug = $grade->slug;
            }

            if ($effectiveSlug && ! $validator->errors()->has('slug')) {
                $slugExists = CommodityGrade::withTrashed()
                    ->where('commodity_id', $effectiveCommodityId)
                    ->where('slug', $effectiveSlug)
                    ->where('id', '!=', $grade->id)
                    ->exists();

                if ($slugExists) {
                    $validator->errors()->add('slug', 'The slug has already been taken for this commodity.');
                }
            }
        });
    }
}
