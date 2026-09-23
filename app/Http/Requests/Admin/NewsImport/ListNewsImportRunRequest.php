<?php

namespace App\Http\Requests\Admin\NewsImport;

use App\Enums\NewsImportStatus;
use App\Models\NewsImportRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNewsImportRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status'      => ['nullable', 'string', Rule::in(NewsImportStatus::values())],
            'source_id'   => ['nullable', 'integer', 'min:1'],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by'     => ['nullable', 'string', Rule::in(NewsImportRun::ALLOWED_SORT_COLUMNS)],
            'sort_order'  => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
