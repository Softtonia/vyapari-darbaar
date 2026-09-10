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
                'projectId' => $this->project_id,
                'messagingSenderId' => $this->messaging_sender_id,
                'appId' => $this->app_id,
                'authDomain' => $this->auth_domain,
                'storageBucket' => $this->storage_bucket,
            ],
            'api_key' => $this->api_key,
            'auth_domain' => $this->auth_domain,
            'project_id' => $this->project_id,
            'storage_bucket' => $this->storage_bucket,
            'messaging_sender_id' => $this->messaging_sender_id,
            'app_id' => $this->app_id,
            'vapid_key' => $this->vapid_key,
            'service_account_configured' => ! empty($this->service_account_json),
            'status' => (bool) $this->status,
        ];
    }
}
