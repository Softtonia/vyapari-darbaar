<?php

namespace Database\Seeders;

use App\Models\CommodityCategory;
use Illuminate\Database\Seeder;

class CommodityCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Grains',
                'slug' => 'grains',
                'description' => 'Wheat, Paddy/Rice, Maize, Barley, Millet and other cereal grains.',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'name' => 'Pulses',
                'slug' => 'pulses',
                'description' => 'Chana (Chickpeas), Tur (Pigeon pea), Moong, Urad, Masoor (Lentils).',
                'sort_order' => 2,
                'status' => true,
            ],
            [
                'name' => 'Oilseeds',
                'slug' => 'oilseeds',
                'description' => 'Soybean, Mustard/Rapeseed, Groundnut, Sunflower seed, Sesame seed, Castor seed.',
                'sort_order' => 3,
                'status' => true,
            ],
            [
                'name' => 'Edible Oils',
                'slug' => 'edible-oils',
                'description' => 'Refined Soy Oil, Mustard Oil, Crude Palm Oil (CPO), Palmolein, Sunflower Oil.',
                'sort_order' => 4,
                'status' => true,
            ],
            [
                'name' => 'Spices',
                'slug' => 'spices',
                'description' => 'Jeera (Cumin), Turmeric, Coriander, Cardamom, Black Pepper, Red Chilli.',
                'sort_order' => 5,
                'status' => true,
            ],
            [
                'name' => 'Dry Fruits / Nuts',
                'slug' => 'dry-fruits-nuts',
                'description' => 'Almonds, Cashews, Walnuts, Raisins, Pistachios, Fox Nuts (Makhana).',
                'sort_order' => 6,
                'status' => true,
            ],
            [
                'name' => 'Sugar & Sweeteners',
                'slug' => 'sugar-sweeteners',
                'description' => 'M-grade Sugar, S-grade Sugar, Jaggery (Gur), Molasses.',
                'sort_order' => 7,
                'status' => true,
            ],
            [
                'name' => 'Feed / By-products',
                'slug' => 'feed-by-products',
                'description' => 'De-oiled Rice Bran (DORB), Soy Meal (DOC), Cottonseed Cake (Khal), Mustard Cake.',
                'sort_order' => 8,
                'status' => true,
            ],
            [
                'name' => 'International Benchmarks',
                'slug' => 'international-benchmarks',
                'description' => 'CBOT Soy, BMD Crude Palm Oil, ICE Sugar, US Wheat & Corn.',
                'sort_order' => 9,
                'status' => true,
            ],
        ];

        foreach ($categories as $cat) {
            CommodityCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }
    }
}
