<?php

namespace App\Http\Requests\Admin\State;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreStateRequest extends FormRequest
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

        $normalizedSlug = ! empty($slug) ? Str::slug($slug) : null;

        $this->merge([
            'name' => $name !== '' ? $name : null,
            'slug' => $normalizedSlug,
            'code' => $code !== '' ? $code : null,
            'status' => $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true : true,
            'sort_order' => $this->has('sort_order') && $this->input('sort_order') !== null && $this->input('sort_order') !== '' ? (int) $this->input('sort_order') : 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('states', 'slug')],
            'code' => ['required', 'string', 'max:20', Rule::unique('states', 'code')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'State name is required.',
            'code.required' => 'State code is required.',
            'code.unique' => 'A state with this code already exists.',
            'slug.unique' => 'A state with this slug already exists.',
        ];
    }
}
