<?php

namespace App\Http\Requests\Admin\Commodity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCommodityRequest extends FormRequest
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
        $nameEn = $this->has('name_en') ? trim((string) $this->input('name_en')) : null;
        $nameHi = $this->has('name_hi') ? trim((string) $this->input('name_hi')) : null;
        $slug = $this->has('slug') && $this->input('slug') !== null ? trim((string) $this->input('slug')) : null;
        $descEn = $this->has('description_en') ? trim((string) $this->input('description_en')) : null;
        $descHi = $this->has('description_hi') ? trim((string) $this->input('description_hi')) : null;

        $normalizedSlug = null;
        if (! empty($slug)) {
            $normalizedSlug = Str::slug($slug);
        } elseif (! empty($nameEn)) {
            $normalizedSlug = Str::slug($nameEn);
        }

        $this->merge([
            'name_en' => $nameEn !== '' ? $nameEn : null,
            'name_hi' => $nameHi !== '' ? $nameHi : null,
            'slug' => $normalizedSlug,
            'description_en' => $descEn !== '' ? $descEn : null,
            'description_hi' => $descHi !== '' ? $descHi : null,
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
            'commodity_category_id' => [
                'required',
                'integer',
                Rule::exists('commodity_categories', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', true),
            ],
            'name_en' => ['required', 'string', 'max:150'],
            'name_hi' => ['nullable', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('commodities', 'slug')],
            'description_en' => ['nullable', 'string'],
            'description_hi' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
