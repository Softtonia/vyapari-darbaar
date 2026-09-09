<?php

namespace App\Http\Requests\Admin\SiteSetting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingRequest extends FormRequest
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
        $sanitized = [];

        if ($this->has('site_name_en') && is_string($this->input('site_name_en'))) {
            $nameEn = trim($this->input('site_name_en'));
            $sanitized['site_name_en'] = $nameEn !== '' ? $nameEn : null;
        }

        if ($this->has('site_name_hi') && is_string($this->input('site_name_hi'))) {
            $nameHi = trim($this->input('site_name_hi'));
            $sanitized['site_name_hi'] = $nameHi !== '' ? $nameHi : null;
        }

        if ($this->has('site_title_en') && is_string($this->input('site_title_en'))) {
            $titleEn = trim($this->input('site_title_en'));
            $sanitized['site_title_en'] = $titleEn !== '' ? $titleEn : null;
        }

        if ($this->has('site_title_hi') && is_string($this->input('site_title_hi'))) {
            $titleHi = trim($this->input('site_title_hi'));
            $sanitized['site_title_hi'] = $titleHi !== '' ? $titleHi : null;
        }

        if ($this->has('site_description_en') && is_string($this->input('site_description_en'))) {
            $descEn = trim($this->input('site_description_en'));
            $sanitized['site_description_en'] = $descEn !== '' ? $descEn : null;
        }

        if ($this->has('site_description_hi') && is_string($this->input('site_description_hi'))) {
            $descHi = trim($this->input('site_description_hi'));
            $sanitized['site_description_hi'] = $descHi !== '' ? $descHi : null;
        }

        if (! empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_name_en' => ['sometimes', 'required', 'string', 'max:150'],
            'site_name_hi' => ['sometimes', 'nullable', 'string', 'max:150'],
            'site_title_en' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_title_hi' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_description_en' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'site_description_hi' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'web_logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'mobile_logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
