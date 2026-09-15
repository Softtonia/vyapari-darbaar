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
                ['name' => 'Wheat', 'slug' => 'wheat', 'code' => 'WHEAT', 'unit' => 'QUINTAL', 'sort_order' => 1],
                ['name' => 'Paddy / Rice', 'slug' => 'paddy-rice', 'code' => 'PADDY_RICE', 'unit' => 'QUINTAL', 'sort_order' => 2],
                ['name' => 'Maize', 'slug' => 'maize', 'code' => 'MAIZE', 'unit' => 'QUINTAL', 'sort_order' => 3],
                ['name' => 'Barley', 'slug' => 'barley', 'code' => 'BARLEY', 'unit' => 'QUINTAL', 'sort_order' => 4],
            ],
            'pulses' => [
                ['name' => 'Chana', 'slug' => 'chana', 'code' => 'CHANA', 'unit' => 'QUINTAL', 'sort_order' => 1],
                ['name' => 'Tur / Arhar', 'slug' => 'tur-arhar', 'code' => 'TUR_ARHAR', 'unit' => 'QUINTAL', 'sort_order' => 2],
                ['name' => 'Moong', 'slug' => 'moong', 'code' => 'MOONG', 'unit' => 'QUINTAL', 'sort_order' => 3],
                ['name' => 'Urad', 'slug' => 'urad', 'code' => 'URAD', 'unit' => 'QUINTAL', 'sort_order' => 4],
                ['name' => 'Masoor', 'slug' => 'masoor', 'code' => 'MASOOR', 'unit' => 'QUINTAL', 'sort_order' => 5],
            ],
            'oilseeds' => [
                ['name' => 'Soybean', 'slug' => 'soybean', 'code' => 'SOYBEAN', 'unit' => 'QUINTAL', 'sort_order' => 1],
                ['name' => 'Mustard / Rapeseed', 'slug' => 'mustard-rapeseed', 'code' => 'MUSTARD', 'unit' => 'QUINTAL', 'sort_order' => 2],
                ['name' => 'Groundnut', 'slug' => 'groundnut', 'code' => 'GROUNDNUT', 'unit' => 'QUINTAL', 'sort_order' => 3],
            ],
            'edible-oils' => [
                ['name' => 'Refined Soy Oil', 'slug' => 'refined-soy-oil', 'code' => 'REFINED_SOY_OIL', 'unit' => '10_KG', 'sort_order' => 1],
                ['name' => 'Mustard Oil', 'slug' => 'mustard-oil', 'code' => 'MUSTARD_OIL', 'unit' => '10_KG', 'sort_order' => 2],
                ['name' => 'Crude Palm Oil (CPO)', 'slug' => 'crude-palm-oil-cpo', 'code' => 'CPO', 'unit' => '10_KG', 'sort_order' => 3],
            ],
            'spices' => [
                ['name' => 'Jeera (Cumin)', 'slug' => 'jeera-cumin', 'code' => 'JEERA', 'unit' => 'QUINTAL', 'sort_order' => 1],
                ['name' => 'Turmeric', 'slug' => 'turmeric', 'code' => 'TURMERIC', 'unit' => 'QUINTAL', 'sort_order' => 2],
                ['name' => 'Coriander', 'slug' => 'coriander', 'code' => 'CORIANDER', 'unit' => 'QUINTAL', 'sort_order' => 3],
            ],
            'dry-fruits-nuts' => [
                ['name' => 'Almonds', 'slug' => 'almonds', 'code' => 'ALMONDS', 'unit' => 'KG', 'sort_order' => 1],
                ['name' => 'Cashews', 'slug' => 'cashews', 'code' => 'CASHEWS', 'unit' => 'KG', 'sort_order' => 2],
            ],
            'sugar-sweeteners' => [
                ['name' => 'Sugar', 'slug' => 'sugar', 'code' => 'SUGAR', 'unit' => 'QUINTAL', 'sort_order' => 1],
                ['name' => 'Jaggery (Gur)', 'slug' => 'jaggery-gur', 'code' => 'JAGGERY', 'unit' => 'QUINTAL', 'sort_order' => 2],
            ],
            'feed-by-products' => [
                ['name' => 'Soy Meal (DOC)', 'slug' => 'soy-meal-doc', 'code' => 'SOY_MEAL_DOC', 'unit' => 'MT', 'sort_order' => 1],
                ['name' => 'Cottonseed Cake', 'slug' => 'cottonseed-cake', 'code' => 'COTTONSEED_CAKE', 'unit' => 'QUINTAL', 'sort_order' => 2],
            ],
            'international-benchmarks' => [
                ['name' => 'CBOT Soybean', 'slug' => 'cbot-soybean', 'code' => 'CBOT_SOYBEAN', 'unit' => 'BUSHEL', 'sort_order' => 1],
                ['name' => 'BMD Crude Palm Oil', 'slug' => 'bmd-crude-palm-oil', 'code' => 'BMD_CPO', 'unit' => 'MT', 'sort_order' => 2],
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
                        'code' => $item['code'],
                        'unit' => $item['unit'],
                        'sort_order' => $item['sort_order'] ?? 0,
                        'status' => true,
                    ]
                );
            }
        }
    }
}
