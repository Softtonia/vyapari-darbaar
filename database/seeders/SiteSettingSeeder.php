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
                'timezone' => 'Asia/Kolkata',
                'default_language' => 'en',
                'currency' => 'INR',
            ]
        );
    }
}
