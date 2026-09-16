<?php

namespace App\Http\Requests\Admin\ExchangeInstrument;

use App\Enums\InstrumentLifecycleStatus;
use App\Enums\InstrumentType;
use App\Models\ExchangeInstrument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ListExchangeInstrumentRequest extends FormRequest
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
            'per_page' => $this->has('per_page') ? (int) $this->input('per_page') : 20,
            'sort_by' => $this->has('sort_by') ? strtolower(trim((string) $this->input('sort_by'))) : 'actual_expiry_date',
            'sort_order' => $this->has('sort_order') ? strtolower(trim((string) $this->input('sort_order'))) : 'asc',
            'search' => $this->has('search') ? trim((string) $this->input('search')) : null,
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
            'sort_by' => ['nullable', 'string', Rule::in(ExchangeInstrument::ALLOWED_SORT_COLUMNS)],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'search' => ['nullable', 'string', 'max:150'],
            'exchange_id' => ['nullable', 'integer', 'exists:exchanges,id'],
            'commodity_id' => ['nullable', 'integer', 'exists:commodities,id'],
            'exchange_commodity_mapping_id' => ['nullable', 'integer', 'exists:exchange_commodity_mappings,id'],
            'instrument_type' => ['nullable', new Enum(InstrumentType::class)],
            'lifecycle_status' => ['nullable', new Enum(InstrumentLifecycleStatus::class)],
            'is_enabled' => ['nullable', 'boolean'],
            'expiry_from' => ['nullable', 'date_format:Y-m-d'],
            'expiry_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:expiry_from'],
        ];
    }
}
