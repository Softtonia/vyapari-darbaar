<?php

namespace App\Http\Requests\Admin\ExchangeInstrument;

use App\Enums\InstrumentLifecycleStatus;
use App\Enums\InstrumentType;
use App\Enums\OptionType;
use App\Models\ExchangeCommodityMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExchangeInstrumentRequest extends FormRequest
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
            'external_instrument_id' => $this->has('external_instrument_id') ? trim((string) $this->input('external_instrument_id')) : null,
            'symbol' => $this->has('symbol') ? trim((string) $this->input('symbol')) : null,
            'instrument_name' => $this->has('instrument_name') ? trim((string) $this->input('instrument_name')) : null,
            'instrument_type' => $this->has('instrument_type') ? strtolower(trim((string) $this->input('instrument_type'))) : null,
            'option_type' => $this->has('option_type') && $this->input('option_type') !== '' ? strtolower(trim((string) $this->input('option_type'))) : null,
            'lifecycle_status' => $this->has('lifecycle_status') ? strtolower(trim((string) $this->input('lifecycle_status'))) : InstrumentLifecycleStatus::ACTIVE->value,
            'is_enabled' => $this->has('is_enabled') ? filter_var($this->input('is_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true : true,
            'quote_unit' => $this->has('quote_unit') ? trim((string) $this->input('quote_unit')) : null,
            'contract_unit' => $this->has('contract_unit') ? trim((string) $this->input('contract_unit')) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $exchangeId = $this->input('exchange_id');

        return [
            'exchange_id' => ['required', 'integer', 'exists:exchanges,id'],
            'exchange_commodity_mapping_id' => ['required', 'integer', 'exists:exchange_commodity_mappings,id'],
            'external_instrument_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique('exchange_instruments', 'external_instrument_id')
                    ->where(fn ($query) => $query->where('exchange_id', $exchangeId)),
            ],
            'symbol' => ['required', 'string', 'max:100'],
            'instrument_name' => ['required', 'string', 'max:150'],
            'instrument_type' => ['required', 'string', Rule::in(InstrumentType::values())],
            'original_expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'actual_expiry_date' => ['required', 'date_format:Y-m-d'],
            'strike_price' => ['nullable', 'numeric', 'min:0', 'required_if:instrument_type,option'],
            'option_type' => ['nullable', 'string', Rule::in(OptionType::values()), 'required_if:instrument_type,option'],
            'lot_size' => ['required', 'numeric', 'min:0.0001'],
            'tick_size' => ['required', 'numeric', 'min:0.0001'],
            'quote_unit' => ['nullable', 'string', 'max:50'],
            'contract_unit' => ['nullable', 'string', 'max:50'],
            'lifecycle_status' => ['nullable', 'string', Rule::in(InstrumentLifecycleStatus::values())],
            'is_enabled' => ['nullable', 'boolean'],
            'listed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $exchangeId = (int) $this->input('exchange_id');
            $mappingId = (int) $this->input('exchange_commodity_mapping_id');

            if ($exchangeId && $mappingId) {
                $mapping = ExchangeCommodityMapping::find($mappingId);
                if ($mapping && (int) $mapping->exchange_id !== $exchangeId) {
                    $validator->errors()->add(
                        'exchange_commodity_mapping_id',
                        'The selected commodity mapping does not belong to the selected exchange.'
                    );
                }
            }
        });
    }
}
