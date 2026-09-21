<?php

namespace App\Http\Requests\Public;

use App\Enums\NewsContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPublicNewsArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        if ($this->has('search')) {
            $search = trim((string) $this->query('search'));
            $sanitized['search'] = $search !== '' ? $search : null;
        }

        // Support category_id and news_category_id alias
        $catId = $this->query('category_id') ?? $this->query('news_category_id');
        if ($catId !== null && $catId !== '') {
            $sanitized['category_id'] = (int) $catId;
        }

        // Support source_id and news_source_id alias
        $srcId = $this->query('source_id') ?? $this->query('news_source_id');
        if ($srcId !== null && $srcId !== '') {
            $sanitized['source_id'] = (int) $srcId;
        }

        if ($this->has('content_type')) {
            $type = strtolower(trim((string) $this->query('content_type')));
            $sanitized['content_type'] = $type !== '' ? $type : null;
        }

        // Support featured and is_featured alias
        $feat = $this->query('featured') ?? $this->query('is_featured');
        if ($feat !== null && $feat !== '') {
            $sanitized['featured'] = filter_var($feat, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        // Support breaking and is_breaking alias
        $brk = $this->query('breaking') ?? $this->query('is_breaking');
        if ($brk !== null && $brk !== '') {
            $sanitized['breaking'] = filter_var($brk, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer'],
            'source_id' => ['nullable', 'integer'],
            'content_type' => ['nullable', Rule::enum(NewsContentType::class)],
            'featured' => ['nullable', 'boolean'],
            'breaking' => ['nullable', 'boolean'],
            'published_from' => ['nullable', 'date'],
            'published_to' => ['nullable', 'date'],
        ];
    }
}
