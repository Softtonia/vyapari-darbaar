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

class StoreNewsArticleRequest extends FormRequest
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
            $sanitized['is_featured'] = filter_var($this->input('is_featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        if ($this->has('is_breaking')) {
            $sanitized['is_breaking'] = filter_var($this->input('is_breaking'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
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
        return [
            'news_source_id' => [
                'required',
                'integer',
                Rule::exists('news_sources', 'id')->whereNull('deleted_at'),
            ],
            'news_category_id' => [
                'required',
                'integer',
                Rule::exists('news_categories', 'id')->whereNull('deleted_at'),
            ],
            'content_type' => ['required', Rule::enum(NewsContentType::class)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:news_articles,slug'],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable'],
            'author_name' => ['nullable', 'string', 'max:150'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'published_on' => ['nullable', 'date'],
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(NewsStatus::class)],
            'is_featured' => ['nullable', 'boolean'],
            'is_breaking' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'array'],
            'meta_keywords.*' => ['string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $status = $this->input('status');

            // Scheduling rules
            if ($status === NewsStatus::SCHEDULED->value) {
                $scheduledAt = $this->input('scheduled_at');
                if (empty($scheduledAt)) {
                    $v->errors()->add('scheduled_at', 'The scheduled_at field is required when status is scheduled.');
                } elseif (strtotime($scheduledAt) <= time()) {
                    $v->errors()->add('scheduled_at', 'The scheduled_at date must be a date in the future.');
                }
            }

            // Publishing rules
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

                // Minimum content rule: must have content, source_url, or featured_image
                $content = trim((string) $this->input('content'));
                $sourceUrl = trim((string) $this->input('source_url'));
                $hasFile = $this->hasFile('featured_image') || ! empty($this->input('featured_image'));

                if ($content === '' && $sourceUrl === '' && ! $hasFile) {
                    $v->errors()->add('content', 'Published articles must have content, media attachment, or a valid source URL.');
                }
            }
        });
    }
}
