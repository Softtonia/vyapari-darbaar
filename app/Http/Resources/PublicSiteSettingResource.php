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
            'site_name_en' => $this->site_name_en,
            'site_name_hi' => $this->site_name_hi,
            'site_title_en' => $this->site_title_en,
            'site_title_hi' => $this->site_title_hi,
            'site_description_en' => $this->site_description_en,
            'site_description_hi' => $this->site_description_hi,
            'web_logo' => $this->web_logo_url,
            'mobile_logo' => $this->mobile_logo_url,
        ];
    }
}
