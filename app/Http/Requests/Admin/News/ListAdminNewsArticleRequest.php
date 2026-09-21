<?php

namespace App\Http\Requests\Admin\News;

use App\Enums\NewsContentType;
use App\Enums\NewsStatus;
use App\Models\NewsArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminNewsArticleRequest extends FormRequest
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

        if ($this->has('status')) {
            $status = strtolower(trim((string) $this->query('status')));
            $sanitized['status'] = $status !== '' ? $status : null;
        }

        if ($this->has('content_type')) {
            $type = strtolower(trim((string) $this->query('content_type')));
            $sanitized['content_type'] = $type !== '' ? $type : null;
        }

        if ($this->has('news_source_id')) {
            $src = $this->query('news_source_id');
            $sanitized['news_source_id'] = ($src !== null && $src !== '') ? (int) $src : null;
        }

        if ($this->has('news_category_id')) {
            $cat = $this->query('news_category_id');
            $sanitized['news_category_id'] = ($cat !== null && $cat !== '') ? (int) $cat : null;
        }

        if ($this->has('is_featured')) {
            $feat = $this->query('is_featured');
            $sanitized['is_featured'] = ($feat !== null && $feat !== '') ? filter_var($feat, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        }

        if ($this->has('is_breaking')) {
            $brk = $this->query('is_breaking');
            $sanitized['is_breaking'] = ($brk !== null && $brk !== '') ? filter_var($brk, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        }

        if ($this->has('sort_by')) {
            $sortBy = strtolower(trim((string) $this->query('sort_by')));
            $sanitized['sort_by'] = $sortBy !== '' ? $sortBy : null;
        }

        if ($this->has('sort_order')) {
            $sortOrder = strtolower(trim((string) $this->query('sort_order')));
            $sanitized['sort_order'] = $sortOrder !== '' ? $sortOrder : null;
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
            'status' => ['nullable', Rule::enum(NewsStatus::class)],
            'content_type' => ['nullable', Rule::enum(NewsContentType::class)],
            'news_source_id' => ['nullable', 'integer'],
            'news_category_id' => ['nullable', 'integer'],
            'is_featured' => ['nullable', 'boolean'],
            'is_breaking' => ['nullable', 'boolean'],
            'published_from' => ['nullable', 'date'],
            'published_to' => ['nullable', 'date'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'string', Rule::in(NewsArticle::ALLOWED_SORT_COLUMNS)],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
