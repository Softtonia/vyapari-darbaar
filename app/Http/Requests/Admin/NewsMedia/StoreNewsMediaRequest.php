<?php

namespace App\Http\Requests\Admin\NewsMedia;

use App\Enums\NewsMediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNewsMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        if ($this->has('language') && is_string($this->input('language'))) {
            $lang = trim($this->input('language'));
            $sanitized['language'] = $lang !== '' ? strtolower($lang) : null;
        }

        if ($this->has('title') && is_string($this->input('title'))) {
            $title = trim($this->input('title'));
            $sanitized['title'] = $title !== '' ? $title : null;
        }

        if ($this->has('external_url') && is_string($this->input('external_url'))) {
            $url = trim($this->input('external_url'));
            $sanitized['external_url'] = $url !== '' ? $url : null;
        }

        if ($this->has('sort_order')) {
            $order = $this->input('sort_order');
            $sanitized['sort_order'] = ($order !== null && $order !== '') ? (int) $order : 0;
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    public function rules(): array
    {
        return [
            'media_type' => ['required', Rule::enum(NewsMediaType::class)],
            'language' => ['nullable', 'string', 'max:10'],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:10240'], // 10MB max
            'external_url' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasFile = $this->hasFile('file');
            $externalUrl = trim((string) $this->input('external_url'));

            if (! $hasFile && $externalUrl === '') {
                $v->errors()->add('file', 'Either a media file upload or an external URL must be provided.');
            }

            if ($hasFile) {
                $mediaType = $this->input('media_type');
                $file = $this->file('file');
                $ext = strtolower($file->getClientOriginalExtension());

                if ($mediaType === NewsMediaType::IMAGE->value && ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $v->errors()->add('file', 'Image files must be of type: jpg, jpeg, png, webp.');
                }

                if ($mediaType === NewsMediaType::PDF->value && $ext !== 'pdf') {
                    $v->errors()->add('file', 'PDF files must have .pdf extension.');
                }
            }
        });
    }
}
