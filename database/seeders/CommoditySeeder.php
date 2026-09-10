<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\CommodityCategory;
use Illuminate\Database\Seeder;

class CommoditySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Category slug => Array of commodities
        $catalog = [
            'grains' => [
                ['name' => 'Wheat', 'slug' => 'wheat', 'sort_order' => 1],
                ['name' => 'Paddy / Rice', 'slug' => 'paddy-rice', 'sort_order' => 2],
                ['name' => 'Maize', 'slug' => 'maize', 'sort_order' => 3],
                ['name' => 'Barley', 'slug' => 'barley', 'sort_order' => 4],
            ],
            'pulses' => [
                ['name' => 'Chana', 'slug' => 'chana', 'sort_order' => 1],
                ['name' => 'Tur / Arhar', 'slug' => 'tur-arhar', 'sort_order' => 2],
                ['name' => 'Moong', 'slug' => 'moong', 'sort_order' => 3],
                ['name' => 'Urad', 'slug' => 'urad', 'sort_order' => 4],
                ['name' => 'Masoor', 'slug' => 'masoor', 'sort_order' => 5],
            ],
            'oilseeds' => [
                ['name' => 'Soybean', 'slug' => 'soybean', 'sort_order' => 1],
                ['name' => 'Mustard / Rapeseed', 'slug' => 'mustard-rapeseed', 'sort_order' => 2],
                ['name' => 'Groundnut', 'slug' => 'groundnut', 'sort_order' => 3],
            ],
            'edible-oils' => [
                ['name' => 'Refined Soy Oil', 'slug' => 'refined-soy-oil', 'sort_order' => 1],
                ['name' => 'Mustard Oil', 'slug' => 'mustard-oil', 'sort_order' => 2],
                ['name' => 'Crude Palm Oil (CPO)', 'slug' => 'crude-palm-oil-cpo', 'sort_order' => 3],
            ],
            'spices' => [
                ['name' => 'Jeera (Cumin)', 'slug' => 'jeera-cumin', 'sort_order' => 1],
                ['name' => 'Turmeric', 'slug' => 'turmeric', 'sort_order' => 2],
                ['name' => 'Coriander', 'slug' => 'coriander', 'sort_order' => 3],
            ],
            'dry-fruits-nuts' => [
                ['name' => 'Almonds', 'slug' => 'almonds', 'sort_order' => 1],
                ['name' => 'Cashews', 'slug' => 'cashews', 'sort_order' => 2],
            ],
            'sugar-sweeteners' => [
                ['name' => 'Sugar', 'slug' => 'sugar', 'sort_order' => 1],
                ['name' => 'Jaggery (Gur)', 'slug' => 'jaggery-gur', 'sort_order' => 2],
            ],
            'feed-by-products' => [
                ['name' => 'Soy Meal (DOC)', 'slug' => 'soy-meal-doc', 'sort_order' => 1],
                ['name' => 'Cottonseed Cake', 'slug' => 'cottonseed-cake', 'sort_order' => 2],
            ],
            'international-benchmarks' => [
                ['name' => 'CBOT Soybean', 'slug' => 'cbot-soybean', 'sort_order' => 1],
                ['name' => 'BMD Crude Palm Oil', 'slug' => 'bmd-crude-palm-oil', 'sort_order' => 2],
            ],
        ];

        foreach ($catalog as $categorySlug => $commodities) {
            $category = CommodityCategory::where('slug', $categorySlug)->first();

            if (! $category) {
                continue;
            }

            foreach ($commodities as $item) {
                Commodity::updateOrCreate(
                    ['slug' => $item['slug']],
                    [
                        'commodity_category_id' => $category->id,
                        'name' => $item['name'],
                        'sort_order' => $item['sort_order'] ?? 0,
                        'status' => true,
                    ]
                );
            }
        }
    }
}
