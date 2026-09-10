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

        if ($this->has('site_name') && is_string($this->input('site_name'))) {
            $name = trim($this->input('site_name'));
            $sanitized['site_name'] = $name !== '' ? $name : null;
        }

        if ($this->has('site_title') && is_string($this->input('site_title'))) {
            $title = trim($this->input('site_title'));
            $sanitized['site_title'] = $title !== '' ? $title : null;
        }

        if ($this->has('site_description') && is_string($this->input('site_description'))) {
            $desc = trim($this->input('site_description'));
            $sanitized['site_description'] = $desc !== '' ? $desc : null;
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
            'site_name' => ['sometimes', 'required', 'string', 'max:150'],
            'site_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'web_logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'mobile_logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
