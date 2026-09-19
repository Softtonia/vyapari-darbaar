<?php

namespace App\Http\Requests\Admin\ExchangeCommodityMapping;

use App\Models\ExchangeCommodityMapping;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExchangeCommodityMappingRequest extends FormRequest
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

        if ($this->has('external_symbol')) {
            $symbol = trim((string) $this->input('external_symbol'));
            $mergeData['external_symbol'] = $symbol !== '' ? $symbol : null;
        }

        if ($this->has('external_code')) {
            $code = trim((string) $this->input('external_code'));
            $mergeData['external_code'] = $code !== '' ? $code : null;
        }

        if ($this->has('external_name')) {
            $name = trim((string) $this->input('external_name'));
            $mergeData['external_name'] = $name !== '' ? $name : null;
        }

        if ($this->has('status')) {
            $mergeData['status'] = filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ExchangeCommodityMapping|null $mapping */
        $mapping = $this->route('mapping') ?? $this->route('exchange_commodity_mapping');
        $mappingModel = $mapping instanceof ExchangeCommodityMapping ? $mapping : ExchangeCommodityMapping::find($mapping);
        $mappingId = $mappingModel?->id;
        $exchangeId = $this->input('exchange_id', $mappingModel?->exchange_id);

        return [
            'exchange_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('exchanges', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', true),
            ],
            'commodity_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('commodities', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', true),
            ],
            'external_symbol' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('exchange_commodity_mappings', 'external_symbol')
                    ->where('exchange_id', $exchangeId)
                    ->ignore($mappingId)
                    ->withoutTrashed(),
            ],
            'external_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'external_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'status' => ['sometimes', 'nullable', 'boolean'],
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
            'exchange_id.exists' => 'The selected exchange is invalid or inactive.',
            'commodity_id.exists' => 'The selected commodity is invalid or inactive.',
            'external_symbol.unique' => 'This external symbol is already mapped on the selected exchange.',
        ];
    }
}
