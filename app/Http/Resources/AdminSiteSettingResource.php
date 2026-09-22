<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSiteSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_name' => $this->site_name,
            'site_title' => $this->site_title,
            'site_description' => $this->site_description,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'social_links' => ! empty($this->social_links) ? $this->social_links : (object) [],
            'timezone' => $this->timezone,
            'default_language' => $this->default_language,
            'currency' => $this->currency,
            'web_logo' => $this->web_logo_url,
            'mobile_logo' => $this->mobile_logo_url,
            'favicon' => $this->favicon_url,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
