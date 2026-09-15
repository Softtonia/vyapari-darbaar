<?php

namespace App\Http\Requests\Admin\Commodity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCommodityRequest extends FormRequest
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

        if ($this->has('commodity_category_id')) {
            $catId = $this->input('commodity_category_id');
            $sanitized['commodity_category_id'] = ($catId !== null && $catId !== '') ? (int) $catId : null;
        }

        if ($this->has('name')) {
            $name = trim((string) $this->input('name'));
            $sanitized['name'] = $name !== '' ? $name : null;
        }

        if ($this->has('slug')) {
            $slug = trim((string) $this->input('slug'));
            $sanitized['slug'] = $slug !== '' ? Str::slug($slug) : null;
        }

        if ($this->has('code')) {
            $code = strtoupper(trim((string) $this->input('code')));
            $sanitized['code'] = $code !== '' ? $code : null;
        }

        if ($this->has('unit')) {
            $unit = strtoupper(trim((string) $this->input('unit')));
            $sanitized['unit'] = $unit !== '' ? $unit : null;
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
        $commodity = $this->route('commodity');
        $commodityId = is_object($commodity) ? $commodity->id : $commodity;
        $currentCategoryId = is_object($commodity) ? $commodity->commodity_category_id : null;
        $submittedCategoryId = $this->input('commodity_category_id');

        $categoryRule = ['sometimes', 'required', 'integer'];
        if ($submittedCategoryId && (int) $submittedCategoryId !== (int) $currentCategoryId) {
            $categoryRule[] = Rule::exists('commodity_categories', 'id')
                ->whereNull('deleted_at')
                ->where('status', true);
        } else {
            $categoryRule[] = Rule::exists('commodity_categories', 'id')
                ->whereNull('deleted_at');
        }

        return [
            'commodity_category_id' => $categoryRule,
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', Rule::unique('commodities', 'slug')->ignore($commodityId)],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('commodities', 'code')->ignore($commodityId)],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Commodity code is required.',
            'code.unique' => 'A commodity with this code already exists.',
            'unit.required' => 'Commodity unit is required.',
            'image.image' => 'The image must be a valid image file.',
            'image.mimes' => 'The image must be a file of type: jpg, jpeg, png, webp.',
            'image.max' => 'The image size may not exceed 2MB.',
        ];
    }
}
