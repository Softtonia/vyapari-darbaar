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

        $rawEmail = $this->has('email') ? $this->input('email') : ($this->has('admin_email') ? $this->input('admin_email') : null);
        if ($rawEmail !== null && is_string($rawEmail)) {
            $email = trim($rawEmail);
            $sanitized['email'] = $email !== '' ? strtolower($email) : null;
        }

        $rawPhone = $this->has('phone_number') ? $this->input('phone_number') : ($this->has('phone') ? $this->input('phone') : null);
        if ($rawPhone !== null && is_string($rawPhone)) {
            $phone = trim($rawPhone);
            $sanitized['phone_number'] = $phone !== '' ? $phone : null;
        }

        if ($this->has('timezone') && is_string($this->input('timezone'))) {
            $tz = trim($this->input('timezone'));
            $sanitized['timezone'] = $tz !== '' ? $tz : null;
        }

        if ($this->has('default_language') && is_string($this->input('default_language'))) {
            $lang = trim($this->input('default_language'));
            $sanitized['default_language'] = $lang !== '' ? strtolower($lang) : null;
        }

        if ($this->has('currency') && is_string($this->input('currency'))) {
            $curr = trim($this->input('currency'));
            $sanitized['currency'] = $curr !== '' ? strtoupper($curr) : null;
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
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:150'],
            'admin_email' => ['sometimes', 'nullable', 'string', 'email', 'max:150'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:100', 'timezone:all'],
            'default_language' => ['sometimes', 'nullable', 'string', 'max:20'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:20'],
            'web_logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'mobile_logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'favicon' => ['sometimes', 'file', 'mimes:ico,png,jpg,jpeg,webp,svg', 'max:2048'],
        ];
    }
}
