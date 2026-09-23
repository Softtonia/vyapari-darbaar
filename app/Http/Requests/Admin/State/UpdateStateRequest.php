<?php

namespace App\Http\Requests\Admin\State;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        if ($this->has('name')) {
            $name = trim((string) $this->input('name'));
            $sanitized['name'] = $name !== '' ? $name : null;
        }

        if ($this->has('slug')) {
            $slug = trim((string) $this->input('slug'));
            $sanitized['slug'] = $slug !== '' ? Str::slug($slug) : null;
        }

        if ($this->has('code')) {
            $code = strtoupper(trim((string) $this->input('code')));
            $sanitized['code'] = $code !== '' ? $code : null;
        }

        if ($this->has('sort_order')) {
            $sortOrder = $this->input('sort_order');
            $sanitized['sort_order'] = ($sortOrder !== null && $sortOrder !== '') ? (int) $sortOrder : null;
        }

        if ($this->has('status')) {
            $sanitized['status'] = filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $this->merge($sanitized);
    }

    public function rules(): array
    {
        $state = $this->route('state');
        $stateId = is_object($state) ? $state->id : $state;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:180', Rule::unique('states', 'slug')->ignore($stateId)],
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('states', 'code')->ignore($stateId)],
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
