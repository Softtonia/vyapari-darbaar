<?php

namespace App\Http\Requests\Admin\News;

use App\Enums\NewsContentType;
use App\Enums\NewsStatus;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNewsArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        if ($this->has('title') && is_string($this->input('title'))) {
            $sanitized['title'] = trim($this->input('title'));
        }

        if ($this->has('slug') && is_string($this->input('slug'))) {
            $slug = trim($this->input('slug'));
            $sanitized['slug'] = $slug !== '' ? Str::slug($slug) : null;
        }

        if ($this->has('short_description') && is_string($this->input('short_description'))) {
            $desc = trim($this->input('short_description'));
            $sanitized['short_description'] = $desc !== '' ? $desc : null;
        }

        if ($this->has('author_name') && is_string($this->input('author_name'))) {
            $author = trim($this->input('author_name'));
            $sanitized['author_name'] = $author !== '' ? $author : null;
        }

        if ($this->has('source_url') && is_string($this->input('source_url'))) {
            $sourceUrl = trim($this->input('source_url'));
            $sanitized['source_url'] = $sourceUrl !== '' ? $sourceUrl : null;
        }

        if ($this->has('is_featured')) {
            $sanitized['is_featured'] = filter_var($this->input('is_featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('is_breaking')) {
            $sanitized['is_breaking'] = filter_var($this->input('is_breaking'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('meta_title') && is_string($this->input('meta_title'))) {
            $mt = trim($this->input('meta_title'));
            $sanitized['meta_title'] = $mt !== '' ? $mt : null;
        }

        if ($this->has('meta_description') && is_string($this->input('meta_description'))) {
            $md = trim($this->input('meta_description'));
            $sanitized['meta_description'] = $md !== '' ? $md : null;
        }

        if ($this->has('meta_keywords')) {
            $rawKeywords = $this->input('meta_keywords');
            if (is_string($rawKeywords)) {
                $decoded = json_decode($rawKeywords, true);
                if (is_array($decoded)) {
                    $rawKeywords = $decoded;
                } else {
                    $rawKeywords = array_map('trim', explode(',', $rawKeywords));
                }
            }
            if (is_array($rawKeywords)) {
                $sanitized['meta_keywords'] = array_values(array_filter(array_map('trim', $rawKeywords), fn ($k) => $k !== ''));
            }
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    public function rules(): array
    {
        $articleId = $this->route('news_article')?->id ?? $this->route('id') ?? $this->input('id');

        return [
            'news_source_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('news_sources', 'id')->whereNull('deleted_at'),
            ],
            'news_category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('news_categories', 'id')->whereNull('deleted_at'),
            ],
            'content_type' => ['sometimes', 'required', Rule::enum(NewsContentType::class)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('news_articles', 'slug')->ignore($articleId)],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'content' => ['sometimes', 'nullable', 'string'],
            'featured_image' => ['sometimes', 'nullable'],
            'author_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'source_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'published_on' => ['sometimes', 'nullable', 'date'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'required', Rule::enum(NewsStatus::class)],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
            'is_breaking' => ['sometimes', 'nullable', 'boolean'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'meta_keywords' => ['sometimes', 'nullable', 'array'],
            'meta_keywords.*' => ['string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $status = $this->input('status');

            if ($status === NewsStatus::SCHEDULED->value) {
                $scheduledAt = $this->input('scheduled_at');
                if (empty($scheduledAt)) {
                    $v->errors()->add('scheduled_at', 'The scheduled_at field is required when status is scheduled.');
                }
            }

            if ($status === NewsStatus::PUBLISHED->value) {
                $sourceId = $this->input('news_source_id');
                if ($sourceId) {
                    $source = NewsSource::find($sourceId);
                    if ($source && ! $source->status) {
                        $v->errors()->add('news_source_id', 'Cannot publish article with an inactive news source.');
                    }
                }

                $categoryId = $this->input('news_category_id');
                if ($categoryId) {
                    $category = NewsCategory::find($categoryId);
                    if ($category && ! $category->status) {
                        $v->errors()->add('news_category_id', 'Cannot publish article with an inactive news category.');
                    }
                }
            }
        });
    }
}
