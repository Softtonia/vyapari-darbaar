<?php

namespace App\Http\Requests\Admin\SmtpSetting;

use App\Models\SmtpSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSmtpSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $existing = SmtpSetting::query()->find(1);
        $passwordRule = ($existing && ! empty($existing->password))
            ? ['nullable', 'string', 'max:500']
            : ['required', 'string', 'max:500'];

        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'scheme' => ['required', 'string', 'in:smtp,smtps'],
            'username' => ['required', 'string', 'max:255'],
            'password' => $passwordRule,
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:150'],
            'status' => ['required', 'boolean'],
        ];
    }
}
