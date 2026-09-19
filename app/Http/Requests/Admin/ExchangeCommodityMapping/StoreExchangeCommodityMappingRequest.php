<?php

namespace App\Http\Requests\Admin\ExchangeCommodityMapping;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeCommodityMappingRequest extends FormRequest
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
        $symbol = $this->has('external_symbol') ? trim((string) $this->input('external_symbol')) : null;
        $code = $this->has('external_code') && $this->input('external_code') !== null ? trim((string) $this->input('external_code')) : null;
        $name = $this->has('external_name') && $this->input('external_name') !== null ? trim((string) $this->input('external_name')) : null;

        $this->merge([
            'external_symbol' => $symbol !== '' ? $symbol : null,
            'external_code' => $code !== '' ? $code : null,
            'external_name' => $name !== '' ? $name : null,
            'status' => $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true : true,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $exchangeId = $this->input('exchange_id');

        return [
            'exchange_id' => [
                'required',
                'integer',
                Rule::exists('exchanges', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', true),
            ],
            'commodity_id' => [
                'required',
                'integer',
                Rule::exists('commodities', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', true),
            ],
            'external_symbol' => [
                'required',
                'string',
                'max:100',
                Rule::unique('exchange_commodity_mappings', 'external_symbol')
                    ->where('exchange_id', $exchangeId)
                    ->withoutTrashed(),
            ],
            'external_code' => ['nullable', 'string', 'max:100'],
            'external_name' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exchange_id.required' => 'Exchange is required.',
            'exchange_id.exists' => 'The selected exchange is invalid or inactive.',
            'commodity_id.required' => 'Commodity is required.',
            'commodity_id.exists' => 'The selected commodity is invalid or inactive.',
            'external_symbol.required' => 'External exchange symbol is required.',
            'external_symbol.unique' => 'This external symbol is already mapped on the selected exchange.',
        ];
    }
}
