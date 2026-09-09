<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmtpSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'mailer' => $this->mailer ?? 'smtp',
            'host' => $this->host,
            'port' => (int) $this->port,
            'username' => $this->username,
            'from_email' => $this->from_email ?? $this->from_address,
            'from_address' => $this->from_email ?? $this->from_address,
            'from_name' => $this->from_name,
            'encryption' => $this->encryption,
            'status' => $this->status ?? 'active',
            'password_configured' => ! empty($this->password),
        ];
    }
}
