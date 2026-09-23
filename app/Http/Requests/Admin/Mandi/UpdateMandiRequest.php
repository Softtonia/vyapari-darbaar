<?php

namespace App\Http\Requests\Admin\Mandi;

use App\Enums\MarketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateMandiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        if ($this->has('district_id')) {
            $districtId = $this->input('district_id');
            $sanitized['district_id'] = ($districtId !== null && $districtId !== '') ? (int) $districtId : null;
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

        if ($this->has('market_type')) {
            $marketType = $this->input('market_type');
            $sanitized['market_type'] = ($marketType !== null && $marketType !== '') ? strtolower(trim((string) $marketType)) : 'apmc';
        }

        if ($this->has('address')) {
            $address = trim((string) $this->input('address'));
            $sanitized['address'] = $address !== '' ? $address : null;
        }

        if ($this->has('pincode')) {
            $pincode = trim((string) $this->input('pincode'));
            $sanitized['pincode'] = $pincode !== '' ? $pincode : null;
        }

        if ($this->has('latitude')) {
            $lat = $this->input('latitude');
            $sanitized['latitude'] = ($lat !== null && $lat !== '') ? (float) $lat : null;
        }

        if ($this->has('longitude')) {
            $lng = $this->input('longitude');
            $sanitized['longitude'] = ($lng !== null && $lng !== '') ? (float) $lng : null;
        }

        if ($this->has('contact_phone')) {
            $phone = trim((string) $this->input('contact_phone'));
            $sanitized['contact_phone'] = $phone !== '' ? $phone : null;
        }

        if ($this->has('contact_email')) {
            $email = trim((string) $this->input('contact_email'));
            $sanitized['contact_email'] = $email !== '' ? $email : null;
        }

        if ($this->has('website')) {
            $website = trim((string) $this->input('website'));
            $sanitized['website'] = $website !== '' ? $website : null;
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
        $mandi = $this->route('mandi');
        $mandiId = is_object($mandi) ? $mandi->id : $mandi;
        $currentDistrictId = is_object($mandi) ? $mandi->district_id : null;
        $submittedDistrictId = $this->input('district_id') ?? $currentDistrictId;

        $districtRule = ['sometimes', 'required', 'integer'];
        if ($this->has('district_id') && (int) $this->input('district_id') !== (int) $currentDistrictId) {
            $districtRule[] = Rule::exists('districts', 'id')
                ->whereNull('deleted_at')
                ->where('status', true);
        } else {
            $districtRule[] = Rule::exists('districts', 'id')
                ->whereNull('deleted_at');
        }

        return [
            'district_id' => $districtRule,
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:200',
                Rule::unique('mandis', 'slug')
                    ->where('district_id', $submittedDistrictId)
                    ->ignore($mandiId),
            ],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('mandis', 'code')->ignore($mandiId)],
            'market_type' => ['nullable', 'string', Rule::enum(MarketType::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'pincode' => ['nullable', 'string', 'regex:/^[0-9]{6}$/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'district_id.required' => 'District is required.',
            'district_id.exists' => 'The selected district is invalid or inactive.',
            'name.required' => 'Mandi name is required.',
            'code.required' => 'Mandi code is required.',
            'code.unique' => 'A mandi with this code already exists.',
            'slug.unique' => 'A mandi with this slug already exists in the selected district.',
            'pincode.regex' => 'Pincode must be exactly 6 digits.',
            'latitude.between' => 'Latitude must be between -90 and 90 degrees.',
            'longitude.between' => 'Longitude must be between -180 and 180 degrees.',
            'market_type.Illuminate\Validation\Rules\Enum' => 'Invalid market type specified.',
        ];
    }
}
