<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SiteSetting
 */
class PublicSiteSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array for public consumption.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'site_name' => $this->site_name,
            'site_title' => $this->site_title,
            'site_description' => $this->site_description,
            'web_logo' => $this->web_logo_url,
            'mobile_logo' => $this->mobile_logo_url,
        ];
    }
}
