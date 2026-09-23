<?php

namespace App\Http\Requests\Admin\NewsSource;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateNewsSourceRequest extends FormRequest
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

        if ($this->has('code') && is_string($this->input('code'))) {
            $code = trim($this->input('code'));
            $sanitized['code'] = $code !== '' ? strtoupper($code) : null;
        }

        if ($this->has('website_url') && is_string($this->input('website_url'))) {
            $url = trim($this->input('website_url'));
            $sanitized['website_url'] = $url !== '' ? $url : null;
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
        $sourceId = $this->route('news_source')?->id ?? $this->route('id') ?? $this->input('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', Rule::unique('news_sources', 'slug')->ignore($sourceId)],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('news_sources', 'code')->ignore($sourceId)],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'logo' => ['sometimes', 'nullable'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
