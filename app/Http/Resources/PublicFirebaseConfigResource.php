<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicFirebaseConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : $this->resource->toArray();

        return [
            'api_key' => $data['api_key'] ?? '',
            'auth_domain' => $data['auth_domain'] ?? '',
            'project_id' => $data['project_id'] ?? '',
            'storage_bucket' => $data['storage_bucket'] ?? null,
            'messaging_sender_id' => $data['messaging_sender_id'] ?? '',
            'app_id' => $data['app_id'] ?? '',
            'vapid_key' => $data['vapid_key'] ?? '',
        ];
    }
}
