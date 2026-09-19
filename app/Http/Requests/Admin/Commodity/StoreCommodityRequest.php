<?php

namespace App\Http\Requests\Admin\Commodity;

use Illuminate\Contracts\Validation\ValidationRule;
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
        $name = $this->has('name') ? trim((string) $this->input('name')) : null;
        $slug = $this->has('slug') && $this->input('slug') !== null ? trim((string) $this->input('slug')) : null;
        $code = $this->has('code') && $this->input('code') !== null ? strtoupper(trim((string) $this->input('code'))) : null;
        $unit = $this->has('unit') && $this->input('unit') !== null ? strtoupper(trim((string) $this->input('unit'))) : null;
        $desc = $this->has('description') ? trim((string) $this->input('description')) : null;

        $normalizedSlug = null;
        if (! empty($slug)) {
            $normalizedSlug = Str::slug($slug);
        } elseif (! empty($name)) {
            $normalizedSlug = Str::slug($name);
        }

        $this->merge([
            'name' => $name !== '' ? $name : null,
            'slug' => $normalizedSlug,
            'code' => $code !== '' ? $code : null,
            'unit' => $unit !== '' ? $unit : null,
            'description' => $desc !== '' ? $desc : null,
            'status' => $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true : true,
            'sort_order' => $this->has('sort_order') && $this->input('sort_order') !== null && $this->input('sort_order') !== '' ? (int) $this->input('sort_order') : 0,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
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
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('commodities', 'slug')],
            'code' => ['required', 'string', 'max:50', Rule::unique('commodities', 'code')],
            'unit' => ['required', 'string', 'max:30'],
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
