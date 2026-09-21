<?php

namespace App\Http\Requests\Admin\NewsSource;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreNewsSourceRequest extends FormRequest
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
            $sanitized['sort_order'] = ($order !== null && $order !== '') ? (int) $order : 0;
        }

        if ($this->has('status')) {
            $sanitized['status'] = filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', 'unique:news_sources,slug'],
            'code' => ['nullable', 'string', 'max:50', 'unique:news_sources,code'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'logo' => ['nullable'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
