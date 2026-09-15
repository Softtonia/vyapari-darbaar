<?php

namespace App\Http\Requests\Admin\District;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        if ($this->has('state_id')) {
            $stateId = $this->input('state_id');
            $sanitized['state_id'] = ($stateId !== null && $stateId !== '') ? (int) $stateId : null;
        }

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
            $sanitized['sort_order'] = ($sortOrder !== null && $sortOrder !== '') ? (int) $sortOrder : 0;
        }

        if ($this->has('status')) {
            $sanitized['status'] = filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $this->merge($sanitized);
    }

    public function rules(): array
    {
        $district = $this->route('district');
        $districtId = is_object($district) ? $district->id : $district;
        $currentStateId = is_object($district) ? $district->state_id : null;
        $submittedStateId = $this->input('state_id') ?? $currentStateId;

        $stateRule = ['sometimes', 'required', 'integer'];
        if ($this->has('state_id') && (int) $this->input('state_id') !== (int) $currentStateId) {
            $stateRule[] = Rule::exists('states', 'id')
                ->whereNull('deleted_at')
                ->where('status', true);
        } else {
            $stateRule[] = Rule::exists('states', 'id')
                ->whereNull('deleted_at');
        }

        return [
            'state_id' => $stateRule,
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                Rule::unique('districts', 'slug')
                    ->where('state_id', $submittedStateId)
                    ->ignore($districtId),
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
