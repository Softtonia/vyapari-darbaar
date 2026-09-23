<?php

namespace App\Http\Requests\Admin\NewsCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateNewsCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $sanitized['name'] = trim($this->input('name'));
        }

        if ($this->has('slug') && is_string($this->input('slug'))) {
            $slug = trim($this->input('slug'));
            $sanitized['slug'] = $slug !== '' ? Str::slug($slug) : null;
        }

        if ($this->has('description') && is_string($this->input('description'))) {
            $desc = trim($this->input('description'));
            $sanitized['description'] = $desc !== '' ? $desc : null;
        }

        if ($this->has('sort_order')) {
            $order = $this->input('sort_order');
            $sanitized['sort_order'] = ($order !== null && $order !== '') ? (int) $order : null;
        }

        if ($this->has('status')) {
            $sanitized['status'] = filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    public function rules(): array
    {
        $categoryId = $this->route('news_category')?->id ?? $this->route('id') ?? $this->input('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', Rule::unique('news_categories', 'slug')->ignore($categoryId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
