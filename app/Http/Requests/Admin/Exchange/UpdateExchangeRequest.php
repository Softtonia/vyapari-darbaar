<?php

namespace App\Http\Requests\Admin\Exchange;

use App\Enums\ExchangeType;
use App\Models\Exchange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateExchangeRequest extends FormRequest
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

        if ($this->has('name')) {
            $name = trim((string) $this->input('name'));
            $mergeData['name'] = $name !== '' ? $name : null;
        }

        if ($this->has('slug')) {
            $slug = trim((string) $this->input('slug'));
            $mergeData['slug'] = ! empty($slug) ? Str::slug($slug) : null;
        }

        if ($this->has('code')) {
            $code = strtoupper(trim((string) $this->input('code')));
            $mergeData['code'] = $code !== '' ? $code : null;
        }

        if ($this->has('timezone')) {
            $tz = trim((string) $this->input('timezone'));
            $mergeData['timezone'] = $tz !== '' ? $tz : 'Asia/Kolkata';
        }

        if ($this->has('website')) {
            $website = trim((string) $this->input('website'));
            $mergeData['website'] = $website !== '' ? $website : null;
        }

        if ($this->has('default_data_delay_minutes')) {
            $val = $this->input('default_data_delay_minutes');
            $mergeData['default_data_delay_minutes'] = ($val !== null && $val !== '') ? (int) $val : null;
        }

        if ($this->has('sort_order')) {
            $mergeData['sort_order'] = (int) $this->input('sort_order');
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
        /** @var Exchange|null $exchange */
        $exchange = $this->route('exchange');
        $exchangeId = $exchange instanceof Exchange ? $exchange->id : (int) $exchange;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', Rule::unique('exchanges', 'slug')->ignore($exchangeId)->withoutTrashed()],
            'code' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('exchanges', 'code')->ignore($exchangeId)->withoutTrashed()],
            'exchange_type' => ['sometimes', 'required', new Enum(ExchangeType::class)],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:100', 'timezone:all'],
            'website' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'default_data_delay_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
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
            'name.required' => 'Exchange name is required.',
            'code.required' => 'Exchange code is required.',
            'code.unique' => 'An exchange with this code already exists.',
            'slug.unique' => 'An exchange with this slug already exists.',
            'exchange_type.required' => 'Exchange type is required.',
        ];
    }
}
