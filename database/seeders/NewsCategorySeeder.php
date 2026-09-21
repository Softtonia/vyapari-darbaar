<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use Illuminate\Database\Seeder;

class NewsCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Commodity News',
                'slug' => 'commodity-news',
                'description' => 'Real-time and editorial news covering agricultural and industrial commodities.',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'name' => 'Exchange News',
                'slug' => 'exchange-news',
                'description' => 'Circulars, contracts, and updates from commodity exchanges.',
                'sort_order' => 2,
                'status' => true,
            ],
            [
                'name' => 'Agriculture',
                'slug' => 'agriculture',
                'description' => 'Farming, monsoon, harvest updates, and crop acreage trends.',
                'sort_order' => 3,
                'status' => true,
            ],
            [
                'name' => 'Market News',
                'slug' => 'market-news',
                'description' => 'Mandi arrivals, price trends, and physical trading updates across India.',
                'sort_order' => 4,
                'status' => true,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Agri-business developments, trade finance, and supply chain logistics.',
                'sort_order' => 5,
                'status' => true,
            ],
            [
                'name' => 'Regulatory',
                'slug' => 'regulatory',
                'description' => 'Government policies, import/export duties, stock limits, and statutory circulars.',
                'sort_order' => 6,
                'status' => true,
            ],
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Agritech innovations, electronic trading systems, and digital mandi developments.',
                'sort_order' => 7,
                'status' => true,
            ],
            [
                'name' => 'Events',
                'slug' => 'events',
                'description' => 'Trade summits, commodity conventions, expos, and webinars.',
                'sort_order' => 8,
                'status' => true,
            ],
            [
                'name' => 'Market Insights',
                'slug' => 'market-insights',
                'description' => 'In-depth research reports, expert commentary, and market intelligence.',
                'sort_order' => 9,
                'status' => true,
            ],
        ];

        foreach ($categories as $catData) {
            NewsCategory::updateOrCreate(
                ['slug' => $catData['slug']],
                $catData
            );
        }
    }
}
