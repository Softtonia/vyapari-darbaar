<?php

namespace App\Http\Requests\Admin\Exchange;

use App\Enums\ExchangeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreExchangeRequest extends FormRequest
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
        $name = $this->has('name') ? trim((string) $this->input('name')) : null;
        $slug = $this->has('slug') && $this->input('slug') !== null ? trim((string) $this->input('slug')) : null;
        $code = $this->has('code') && $this->input('code') !== null ? strtoupper(trim((string) $this->input('code'))) : null;
        $website = $this->has('website') && $this->input('website') !== null ? trim((string) $this->input('website')) : null;
        $timezone = $this->has('timezone') && $this->input('timezone') !== null ? trim((string) $this->input('timezone')) : 'Asia/Kolkata';

        $normalizedSlug = null;
        if (! empty($slug)) {
            $normalizedSlug = Str::slug($slug);
        } elseif (! empty($name)) {
            $normalizedSlug = Str::slug($name);
        }

        $this->merge([
            'name' => $name !== '' ? $name : null,
            'slug' => $normalizedSlug,
            'code' => $code !== '' ? $code : null,
            'timezone' => $timezone !== '' ? $timezone : 'Asia/Kolkata',
            'website' => $website !== '' ? $website : null,
            'default_data_delay_minutes' => $this->has('default_data_delay_minutes') && $this->input('default_data_delay_minutes') !== null && $this->input('default_data_delay_minutes') !== '' ? (int) $this->input('default_data_delay_minutes') : null,
            'sort_order' => $this->has('sort_order') && $this->input('sort_order') !== null && $this->input('sort_order') !== '' ? (int) $this->input('sort_order') : null,
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
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('exchanges', 'slug')->withoutTrashed()],
            'code' => ['required', 'string', 'max:30', Rule::unique('exchanges', 'code')->withoutTrashed()],
            'exchange_type' => ['required', new Enum(ExchangeType::class)],
            'timezone' => ['nullable', 'string', 'max:100', 'timezone:all'],
            'website' => ['nullable', 'url', 'max:2048'],
            'default_data_delay_minutes' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
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
            'name.required' => 'Exchange name is required.',
            'code.required' => 'Exchange code is required.',
            'code.unique' => 'An exchange with this code already exists.',
            'slug.unique' => 'An exchange with this slug already exists.',
            'exchange_type.required' => 'Exchange type is required.',
        ];
    }
}
