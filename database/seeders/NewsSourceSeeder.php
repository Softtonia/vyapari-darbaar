<?php

namespace Database\Seeders;

use App\Models\NewsSource;
use Illuminate\Database\Seeder;

class NewsSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'name' => 'Vyapari Darbaar',
                'slug' => 'vyapari-darbaar',
                'code' => 'VD',
                'website_url' => 'https://vyaparidarbaar.com',
                'description' => 'Official editorial publications and announcements from Vyapari Darbaar.',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'name' => 'National Commodity & Derivatives Exchange (NCDEX)',
                'slug' => 'ncdex',
                'code' => 'NCDEX',
                'website_url' => 'https://www.ncdex.com',
                'description' => 'Official market releases, circulars, and updates from NCDEX.',
                'sort_order' => 2,
                'status' => true,
            ],
            [
                'name' => 'Multi Commodity Exchange (MCX)',
                'slug' => 'mcx',
                'code' => 'MCX',
                'website_url' => 'https://www.mcxindia.com',
                'description' => 'Official commodity market announcements and press releases from MCX.',
                'sort_order' => 3,
                'status' => true,
            ],
            [
                'name' => 'Securities and Exchange Board of India (SEBI)',
                'slug' => 'sebi',
                'code' => 'SEBI',
                'website_url' => 'https://www.sebi.gov.in',
                'description' => 'Regulatory updates, circulars, and notifications from SEBI.',
                'sort_order' => 4,
                'status' => true,
            ],
            [
                'name' => 'Ministry of Agriculture & Farmers Welfare',
                'slug' => 'ministry-of-agriculture',
                'code' => 'MOA',
                'website_url' => 'https://agricoop.nic.in',
                'description' => 'Policy updates, MSP notifications, and crop estimates from the Ministry of Agriculture.',
                'sort_order' => 5,
                'status' => true,
            ],
        ];

        foreach ($sources as $sourceData) {
            NewsSource::updateOrCreate(
                ['slug' => $sourceData['slug']],
                $sourceData
            );
        }
    }
}
