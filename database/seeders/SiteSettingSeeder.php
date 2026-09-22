<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteSetting::updateOrCreate(
            ['id' => 1],
            [
                'site_name' => 'Vyapari Darbar',
                'email' => 'admin@vyaparidarbaar.com',
                'phone_number' => '+919876543210',
                'social_links' => [
                    'facebook' => 'https://facebook.com/vyaparidarbar',
                    'twitter' => 'https://x.com/vyaparidarbar',
                    'instagram' => 'https://instagram.com/vyaparidarbar',
                    'linkedin' => 'https://linkedin.com/company/vyaparidarbar',
                    'youtube' => 'https://youtube.com/@vyaparidarbar',
                ],
                'timezone' => 'Asia/Kolkata',
                'default_language' => 'en',
                'currency' => 'INR',
            ]
        );
    }
}
