<?php

namespace App\Http\Requests\Admin\District;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->has('name') ? trim((string) $this->input('name')) : null;
        $slug = $this->has('slug') && $this->input('slug') !== null ? trim((string) $this->input('slug')) : null;
        $code = $this->has('code') && $this->input('code') !== null ? strtoupper(trim((string) $this->input('code'))) : null;
        $stateId = $this->input('state_id');

        $normalizedSlug = ! empty($slug) ? Str::slug($slug) : null;

        $this->merge([
            'state_id' => ($stateId !== null && $stateId !== '') ? (int) $stateId : null,
            'name' => $name !== '' ? $name : null,
            'slug' => $normalizedSlug,
            'code' => $code !== '' ? $code : null,
            'status' => $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true : true,
            'sort_order' => $this->has('sort_order') && $this->input('sort_order') !== null && $this->input('sort_order') !== '' ? (int) $this->input('sort_order') : null,
        ]);
    }

    public function rules(): array
    {
        $stateId = $this->input('state_id');

        return [
            'state_id' => [
                'required',
                'integer',
                Rule::exists('states', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', true),
            ],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                Rule::unique('districts', 'slug')
                    ->where('state_id', $stateId),
            ],
            'code' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'state_id.required' => 'State is required.',
            'state_id.exists' => 'The selected state is invalid or inactive.',
            'name.required' => 'District name is required.',
            'slug.unique' => 'A district with this slug already exists in the selected state.',
        ];
    }
}
