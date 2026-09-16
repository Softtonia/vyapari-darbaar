<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketInstrumentHistoryRequest extends FormRequest
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
            'interval' => $this->has('interval') ? strtoupper(trim((string) $this->input('interval'))) : '1D',
            'limit' => $this->has('limit') ? (int) $this->input('limit') : 100,
            'from' => $this->has('from') ? trim((string) $this->input('from')) : null,
            'to' => $this->has('to') ? trim((string) $this->input('to')) : null,
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
            'interval' => ['required', 'string', Rule::in(['1D'])],
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
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
            'interval.in' => "The selected interval is unsupported. Only '1D' (daily EOD) is supported in this phase.",
            'from.before_or_equal' => 'The from date must be a date before or equal to to date.',
            'to.after_or_equal' => 'The to date must be a date after or equal to from date.',
        ];
    }
}
