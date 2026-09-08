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
                ['name_en' => 'Wheat', 'name_hi' => 'गेहूं', 'slug' => 'wheat', 'sort_order' => 1],
                ['name_en' => 'Paddy / Rice', 'name_hi' => 'धान / चावल', 'slug' => 'paddy-rice', 'sort_order' => 2],
                ['name_en' => 'Maize', 'name_hi' => 'मक्का', 'slug' => 'maize', 'sort_order' => 3],
                ['name_en' => 'Barley', 'name_hi' => 'जौ', 'slug' => 'barley', 'sort_order' => 4],
            ],
            'pulses' => [
                ['name_en' => 'Chana', 'name_hi' => 'चना / छोले', 'slug' => 'chana', 'sort_order' => 1],
                ['name_en' => 'Tur / Arhar', 'name_hi' => 'तुअर / अरहर', 'slug' => 'tur-arhar', 'sort_order' => 2],
                ['name_en' => 'Moong', 'name_hi' => 'मूंग', 'slug' => 'moong', 'sort_order' => 3],
                ['name_en' => 'Urad', 'name_hi' => 'उड़द', 'slug' => 'urad', 'sort_order' => 4],
                ['name_en' => 'Masoor', 'name_hi' => 'मसूर', 'slug' => 'masoor', 'sort_order' => 5],
            ],
            'oilseeds' => [
                ['name_en' => 'Soybean', 'name_hi' => 'सोयाबीन', 'slug' => 'soybean', 'sort_order' => 1],
                ['name_en' => 'Mustard / Rapeseed', 'name_hi' => 'सरसों / रायडा', 'slug' => 'mustard-rapeseed', 'sort_order' => 2],
                ['name_en' => 'Groundnut', 'name_hi' => 'मूंगफली', 'slug' => 'groundnut', 'sort_order' => 3],
            ],
            'edible-oils' => [
                ['name_en' => 'Refined Soy Oil', 'name_hi' => 'सोया रिफाइंड तेल', 'slug' => 'refined-soy-oil', 'sort_order' => 1],
                ['name_en' => 'Mustard Oil', 'name_hi' => 'सरसों तेल', 'slug' => 'mustard-oil', 'sort_order' => 2],
                ['name_en' => 'Crude Palm Oil (CPO)', 'name_hi' => 'कच्चा पाम तेल (CPO)', 'slug' => 'crude-palm-oil-cpo', 'sort_order' => 3],
            ],
            'spices' => [
                ['name_en' => 'Jeera (Cumin)', 'name_hi' => 'जीरा', 'slug' => 'jeera-cumin', 'sort_order' => 1],
                ['name_en' => 'Turmeric', 'name_hi' => 'हल्दी', 'slug' => 'turmeric', 'sort_order' => 2],
                ['name_en' => 'Coriander', 'name_hi' => 'धनिया', 'slug' => 'coriander', 'sort_order' => 3],
            ],
            'dry-fruits-nuts' => [
                ['name_en' => 'Almonds', 'name_hi' => 'बादाम', 'slug' => 'almonds', 'sort_order' => 1],
                ['name_en' => 'Cashews', 'name_hi' => 'काजू', 'slug' => 'cashews', 'sort_order' => 2],
            ],
            'sugar-sweeteners' => [
                ['name_en' => 'Sugar', 'name_hi' => 'चीनी', 'slug' => 'sugar', 'sort_order' => 1],
                ['name_en' => 'Jaggery (Gur)', 'name_hi' => 'गुड़', 'slug' => 'jaggery-gur', 'sort_order' => 2],
            ],
            'feed-by-products' => [
                ['name_en' => 'Soy Meal (DOC)', 'name_hi' => 'सोया खली (DOC)', 'slug' => 'soy-meal-doc', 'sort_order' => 1],
                ['name_en' => 'Cottonseed Cake', 'name_hi' => 'बिनौला खल', 'slug' => 'cottonseed-cake', 'sort_order' => 2],
            ],
            'international-benchmarks' => [
                ['name_en' => 'CBOT Soybean', 'name_hi' => 'सीबीओटी सोयाबीन', 'slug' => 'cbot-soybean', 'sort_order' => 1],
                ['name_en' => 'BMD Crude Palm Oil', 'name_hi' => 'बीएमडी पाम तेल', 'slug' => 'bmd-crude-palm-oil', 'sort_order' => 2],
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
                        'name_en' => $item['name_en'],
                        'name_hi' => $item['name_hi'] ?? null,
                        'sort_order' => $item['sort_order'] ?? 0,
                        'status' => true,
                    ]
                );
            }
        }
    }
}
