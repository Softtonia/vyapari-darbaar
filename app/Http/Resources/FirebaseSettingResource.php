<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FirebaseSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'web_config' => [
                'apiKey' => $this->api_key,
                'authDomain' => $this->auth_domain,
                'projectId' => $this->project_id,
                'storageBucket' => $this->storage_bucket,
                'messagingSenderId' => $this->messaging_sender_id,
                'appId' => $this->app_id,
            ],
            'vapid_key' => $this->vapid_key,
            'service_account_configured' => ! empty($this->service_account_json),
            'status' => (bool) $this->status,
        ];
    }
}
