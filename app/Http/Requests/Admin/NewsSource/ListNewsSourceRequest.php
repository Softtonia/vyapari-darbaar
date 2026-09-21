<?php

namespace App\Http\Requests\Admin\NewsSource;

use App\Models\NewsSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNewsSourceRequest extends FormRequest
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
            $status = $this->query('status');
            $sanitized['status'] = ($status !== null && $status !== '') ? filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
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
            'status' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', Rule::in(NewsSource::ALLOWED_SORT_COLUMNS)],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
