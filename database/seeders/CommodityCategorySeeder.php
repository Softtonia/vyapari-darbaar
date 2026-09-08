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
                'name_en' => 'Grains',
                'name_hi' => 'अनाज',
                'slug' => 'grains',
                'description_en' => 'Wheat, Paddy/Rice, Maize, Barley, Millet and other cereal grains.',
                'description_hi' => 'गेहूं, धान/चावल, मक्का, जौ, बाजरा एवं अन्य अनाज।',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'name_en' => 'Pulses',
                'name_hi' => 'दलहन',
                'slug' => 'pulses',
                'description_en' => 'Chana (Chickpeas), Tur (Pigeon pea), Moong, Urad, Masoor (Lentils).',
                'description_hi' => 'चना, तुअर/अरहर, मूंग, उड़द, मसूर एवं अन्य दालें।',
                'sort_order' => 2,
                'status' => true,
            ],
            [
                'name_en' => 'Oilseeds',
                'name_hi' => 'तिलहन',
                'slug' => 'oilseeds',
                'description_en' => 'Soybean, Mustard/Rapeseed, Groundnut, Sunflower seed, Sesame seed, Castor seed.',
                'description_hi' => 'सोयाबीन, सरसों/रायडा, मूंगफली, सूरजमुखी बीज, तिल, अरंडी बीज।',
                'sort_order' => 3,
                'status' => true,
            ],
            [
                'name_en' => 'Edible Oils',
                'name_hi' => 'खाद्य तेल',
                'slug' => 'edible-oils',
                'description_en' => 'Refined Soy Oil, Mustard Oil, Crude Palm Oil (CPO), Palmolein, Sunflower Oil.',
                'description_hi' => 'सोया रिफाइंड तेल, सरसों तेल, कच्चा पाम तेल (CPO), पामोलिन, सूरजमुखी तेल।',
                'sort_order' => 4,
                'status' => true,
            ],
            [
                'name_en' => 'Spices',
                'name_hi' => 'मसाले',
                'slug' => 'spices',
                'description_en' => 'Jeera (Cumin), Turmeric, Coriander, Cardamom, Black Pepper, Red Chilli.',
                'description_hi' => 'जीरा, हल्दी, धनिया, इलायची, काली मिर्च, लाल मिर्च।',
                'sort_order' => 5,
                'status' => true,
            ],
            [
                'name_en' => 'Dry Fruits / Nuts',
                'name_hi' => 'सूखे मेवे',
                'slug' => 'dry-fruits-nuts',
                'description_en' => 'Almonds, Cashews, Walnuts, Raisins, Pistachios, Fox Nuts (Makhana).',
                'description_hi' => 'बादाम, काजू, अखरोट, किशमिश, पिस्ता, मखाना।',
                'sort_order' => 6,
                'status' => true,
            ],
            [
                'name_en' => 'Sugar & Sweeteners',
                'name_hi' => 'चीनी व मीठा',
                'slug' => 'sugar-sweeteners',
                'description_en' => 'M-grade Sugar, S-grade Sugar, Jaggery (Gur), Molasses.',
                'description_hi' => 'एम-ग्रेड चीनी, एस-ग्रेड चीनी, गुड़, राब/शीरा।',
                'sort_order' => 7,
                'status' => true,
            ],
            [
                'name_en' => 'Feed / By-products',
                'name_hi' => 'पशु आहार व उपोत्पाद',
                'slug' => 'feed-by-products',
                'description_en' => 'De-oiled Rice Bran (DORB), Soy Meal (DOC), Cottonseed Cake (Khal), Mustard Cake.',
                'description_hi' => 'डी-ऑयल्ड राइस ब्रान (DORB), सोया खली (DOC), बिनौला खल, सरसों खल।',
                'sort_order' => 8,
                'status' => true,
            ],
            [
                'name_en' => 'International Benchmarks',
                'name_hi' => 'अंतरराष्ट्रीय बेंचमार्क',
                'slug' => 'international-benchmarks',
                'description_en' => 'CBOT Soy, BMD Crude Palm Oil, ICE Sugar, US Wheat & Corn.',
                'description_hi' => 'सीबीओटी सोया, बीएमडी पाम तेल, आईसीई चीनी, यूएस गेहूं व मक्का।',
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
