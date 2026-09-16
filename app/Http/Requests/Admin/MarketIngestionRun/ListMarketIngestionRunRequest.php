<?php

namespace App\Http\Requests\Admin\MarketIngestionRun;

use App\Enums\IngestionSourceType;
use App\Enums\IngestionStatus;
use App\Models\MarketIngestionRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMarketIngestionRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare input for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->has('per_page') ? (int) $this->input('per_page') : 15,
            'sort_by' => $this->has('sort_by') ? strtolower(trim((string) $this->input('sort_by'))) : 'id',
            'sort_order' => $this->has('sort_order') ? strtolower(trim((string) $this->input('sort_order'))) : 'desc',
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in(MarketIngestionRun::ALLOWED_SORT_COLUMNS)],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'exchange_id' => ['nullable', 'integer', 'exists:exchanges,id'],
            'source_type' => ['nullable', 'string', Rule::in(IngestionSourceType::values())],
            'status' => ['nullable', 'string', Rule::in(IngestionStatus::values())],
            'trade_date' => ['nullable', 'date_format:Y-m-d'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
