<?php

namespace App\Http\Requests\Admin\Mandi;

use App\Enums\MarketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreMandiRequest extends FormRequest
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
        $districtId = $this->input('district_id');
        $marketType = $this->has('market_type') && $this->input('market_type') !== null ? strtolower(trim((string) $this->input('market_type'))) : 'apmc';
        $pincode = $this->has('pincode') && $this->input('pincode') !== null ? trim((string) $this->input('pincode')) : null;
        $address = $this->has('address') ? trim((string) $this->input('address')) : null;
        $phone = $this->has('contact_phone') ? trim((string) $this->input('contact_phone')) : null;
        $email = $this->has('contact_email') ? trim((string) $this->input('contact_email')) : null;
        $website = $this->has('website') ? trim((string) $this->input('website')) : null;

        $normalizedSlug = ! empty($slug) ? Str::slug($slug) : null;

        $this->merge([
            'district_id' => ($districtId !== null && $districtId !== '') ? (int) $districtId : null,
            'name' => $name !== '' ? $name : null,
            'slug' => $normalizedSlug,
            'code' => $code !== '' ? $code : null,
            'market_type' => $marketType !== '' ? $marketType : 'apmc',
            'pincode' => $pincode !== '' ? $pincode : null,
            'address' => $address !== '' ? $address : null,
            'contact_phone' => $phone !== '' ? $phone : null,
            'contact_email' => $email !== '' ? $email : null,
            'website' => $website !== '' ? $website : null,
            'status' => $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true : true,
            'sort_order' => $this->has('sort_order') && $this->input('sort_order') !== null && $this->input('sort_order') !== '' ? (int) $this->input('sort_order') : null,
        ]);
    }

    public function rules(): array
    {
        $districtId = $this->input('district_id');

        return [
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', true),
            ],
            'name' => ['required', 'string', 'max:180'],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                Rule::unique('mandis', 'slug')
                    ->where('district_id', $districtId),
            ],
            'code' => ['required', 'string', 'max:50', Rule::unique('mandis', 'code')],
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
