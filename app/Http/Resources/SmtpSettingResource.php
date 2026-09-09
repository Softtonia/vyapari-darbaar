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
            'host' => $this->host,
            'port' => (int) $this->port,
            'scheme' => $this->scheme,
            'username' => $this->username,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'status' => (bool) $this->status,
            'password_configured' => ! empty($this->password),
        ];
    }
}
