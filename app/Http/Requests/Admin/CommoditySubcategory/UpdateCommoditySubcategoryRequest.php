<?php

namespace App\Http\Requests\Admin\CommoditySubcategory;

use App\Models\Commodity;
use App\Models\CommoditySubcategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCommoditySubcategoryRequest extends FormRequest
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

        if ($this->has('name')) {
            $name = trim((string) $this->input('name'));
            $sanitized['name'] = $name !== '' ? $name : null;
        }

        if ($this->has('slug')) {
            $slug = trim((string) $this->input('slug'));
            $sanitized['slug'] = $slug !== '' ? Str::slug($slug) : null;
        }

        if ($this->has('description')) {
            $desc = trim((string) $this->input('description'));
            $sanitized['description'] = $desc !== '' ? $desc : null;
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
        $subcat = $this->route('commoditySubcategory') ?? $this->route('commodity_subcategory');
        $currentCommodityId = $subcat instanceof CommoditySubcategory ? $subcat->commodity_id : null;
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
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
            $subcat = $this->route('commoditySubcategory') ?? $this->route('commodity_subcategory');
            if (! ($subcat instanceof CommoditySubcategory)) {
                $subcat = CommoditySubcategory::find($subcat);
            }

            if (! $subcat) {
                return;
            }

            $submittedCommodityId = $this->input('commodity_id');
            $isChangingCommodity = $submittedCommodityId && (int) $submittedCommodityId !== (int) $subcat->commodity_id;
            $targetCommodityId = $isChangingCommodity ? (int) $submittedCommodityId : (int) $subcat->commodity_id;

            // 1. If moving to another commodity, ensure new commodity and its category are active and non-deleted
            if ($isChangingCommodity && ! $validator->errors()->has('commodity_id')) {
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

            // 2. Determine effective slug: supplied slug if provided, else existing slug
            if ($this->has('slug') && $this->input('slug') !== null && $this->input('slug') !== '') {
                $effectiveSlug = Str::slug($this->input('slug'));
            } else {
                $effectiveSlug = $subcat->slug;
            }

            // 3. Validate uniqueness of (targetCommodityId, effectiveSlug) across non-deleted & soft-deleted rows
            if ($effectiveSlug && ! $validator->errors()->has('slug')) {
                $slugExists = CommoditySubcategory::withTrashed()
                    ->where('commodity_id', $targetCommodityId)
                    ->where('slug', $effectiveSlug)
                    ->where('id', '!=', $subcat->id)
                    ->exists();

                if ($slugExists) {
                    $validator->errors()->add('slug', 'The slug has already been taken for this commodity.');
                }
            }
        });
    }
}
