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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // If from_email is not provided but from_address is, populate from_email
        if (! $this->has('from_email') && $this->has('from_address')) {
            $merge['from_email'] = $this->input('from_address');
        }

        // Normalize boolean or numeric status to 'active' / 'pending'
        if ($this->has('status')) {
            $rawStatus = $this->input('status');
            if ($rawStatus === true || $rawStatus === '1' || $rawStatus === 1 || strtolower((string) $rawStatus) === 'active') {
                $merge['status'] = 'active';
            } elseif ($rawStatus === false || $rawStatus === '0' || $rawStatus === 0 || strtolower((string) $rawStatus) === 'pending' || strtolower((string) $rawStatus) === 'inactive') {
                $merge['status'] = 'pending';
            }
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
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
            'mailer' => ['required', 'string', 'max:50'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => $passwordRule,
            'from_email' => ['required', 'email', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:150'],
            'encryption' => ['nullable', 'string', 'max:20', 'in:tls,ssl,starttls,none,smtp,smtps,null'],
            'status' => ['required', 'string', 'in:active,pending'],
        ];
    }
}
