<?php

namespace App\Http\Requests\Admin\CommodityCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCommodityCategoryRequest extends FormRequest
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
        $category = $this->route('commodityCategory');
        $categoryId = is_object($category) ? $category->id : $category;

        return [
            'name_en' => ['sometimes', 'required', 'string', 'max:150'],
            'name_hi' => ['nullable', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', Rule::unique('commodity_categories', 'slug')->ignore($categoryId)],
            'description_en' => ['nullable', 'string'],
            'description_hi' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
