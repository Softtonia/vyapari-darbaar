<?php

namespace App\Http\Requests\Admin\ExchangeInstrument;

use App\Enums\InstrumentLifecycleStatus;
use App\Enums\InstrumentType;
use App\Enums\OptionType;
use App\Models\ExchangeCommodityMapping;
use App\Models\ExchangeInstrument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateExchangeInstrumentRequest extends FormRequest
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
        $mergeData = [];

        if ($this->has('external_instrument_id')) {
            $mergeData['external_instrument_id'] = trim((string) $this->input('external_instrument_id'));
        }
        if ($this->has('symbol')) {
            $mergeData['symbol'] = trim((string) $this->input('symbol'));
        }
        if ($this->has('instrument_name')) {
            $mergeData['instrument_name'] = trim((string) $this->input('instrument_name'));
        }
        if ($this->has('instrument_type')) {
            $mergeData['instrument_type'] = strtolower(trim((string) $this->input('instrument_type')));
        }
        if ($this->has('option_type')) {
            $mergeData['option_type'] = $this->input('option_type') !== '' ? strtolower(trim((string) $this->input('option_type'))) : null;
        }
        if ($this->has('lifecycle_status')) {
            $mergeData['lifecycle_status'] = strtolower(trim((string) $this->input('lifecycle_status')));
        }
        if ($this->has('is_enabled')) {
            $mergeData['is_enabled'] = filter_var($this->input('is_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->input('is_enabled');
        }
        if ($this->has('quote_unit')) {
            $mergeData['quote_unit'] = trim((string) $this->input('quote_unit'));
        }
        if ($this->has('contract_unit')) {
            $mergeData['contract_unit'] = trim((string) $this->input('contract_unit'));
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ExchangeInstrument|null $instrument */
        $instrument = $this->route('instrument');
        $exchangeId = $instrument ? $instrument->exchange_id : $this->input('exchange_id');

        return [
            'exchange_commodity_mapping_id' => ['nullable', 'integer', 'exists:exchange_commodity_mappings,id'],
            'external_instrument_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('exchange_instruments', 'external_instrument_id')
                    ->where(fn ($query) => $query->where('exchange_id', $exchangeId))
                    ->ignore($instrument?->id),
            ],
            'symbol' => ['nullable', 'string', 'max:100'],
            'instrument_name' => ['nullable', 'string', 'max:150'],
            'instrument_type' => ['nullable', 'string', Rule::in(InstrumentType::values())],
            'original_expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'actual_expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'strike_price' => ['nullable', 'numeric', 'min:0'],
            'option_type' => ['nullable', 'string', Rule::in(OptionType::values())],
            'lot_size' => ['nullable', 'numeric', 'min:0.0001'],
            'tick_size' => ['nullable', 'numeric', 'min:0.0001'],
            'quote_unit' => ['nullable', 'string', 'max:50'],
            'contract_unit' => ['nullable', 'string', 'max:50'],
            'lifecycle_status' => ['nullable', 'string', Rule::in(InstrumentLifecycleStatus::values())],
            'is_enabled' => ['nullable', 'boolean'],
            'listed_at' => ['nullable', 'date'],
            'delisted_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var ExchangeInstrument|null $instrument */
            $instrument = $this->route('instrument');
            $mappingId = $this->input('exchange_commodity_mapping_id');

            if ($instrument && $mappingId) {
                $mapping = ExchangeCommodityMapping::find((int) $mappingId);
                if ($mapping && (int) $mapping->exchange_id !== (int) $instrument->exchange_id) {
                    $validator->errors()->add(
                        'exchange_commodity_mapping_id',
                        'The selected commodity mapping does not belong to the instrument exchange.'
                    );
                }
            }
        });
    }
}
