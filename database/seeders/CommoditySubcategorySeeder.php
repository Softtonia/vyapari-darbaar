<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\CommoditySubcategory;
use Illuminate\Database\Seeder;

class CommoditySubcategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Commodity slug => Array of subcategories
        $catalog = [
            'wheat' => [
                [
                    'name' => 'Lokwan Wheat',
                    'slug' => 'lokwan-wheat',
                    'description' => 'Popular semi-hard wheat variety widely traded across MP, Maharashtra, and Gujarat.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Sharbati Wheat',
                    'slug' => 'sharbati-wheat',
                    'description' => 'Premium quality golden grain wheat, famous for sweet and soft chapatis (Sehore Sharbati).',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Milling Quality Wheat',
                    'slug' => 'milling-quality-wheat',
                    'description' => 'Standard commercial wheat procured for flour and maida roller mills.',
                    'sort_order' => 3,
                ],
                [
                    'name' => 'Tukdi Wheat',
                    'slug' => 'tukdi-wheat',
                    'description' => 'Gujarat tukdi variety known for luster, hardness, and high protein.',
                    'sort_order' => 4,
                ],
                [
                    'name' => 'Malavraj (Durum Wheat)',
                    'slug' => 'malavraj-durum-wheat',
                    'description' => 'Hard durum wheat used extensively for pasta, semolina (suji), and vermicelli.',
                    'sort_order' => 5,
                ],
            ],
            'paddy-rice' => [
                [
                    'name' => 'Basmati 1121',
                    'slug' => 'basmati-1121',
                    'description' => 'Extra long grain premium export quality Basmati variety.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Basmati 1509',
                    'slug' => 'basmati-1509',
                    'description' => 'Early maturing aromatic long grain Basmati variety.',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Basmati 1718',
                    'slug' => 'basmati-1718',
                    'description' => 'Blight resistant improved successor of 1121 variety.',
                    'sort_order' => 3,
                ],
                [
                    'name' => 'Sugandha Paddy',
                    'slug' => 'sugandha-paddy',
                    'description' => 'Semi-aromatic medium long grain non-basmati rice variety.',
                    'sort_order' => 4,
                ],
                [
                    'name' => 'PR 14 / Parmal',
                    'slug' => 'pr-14-parmal',
                    'description' => 'Standard non-basmati high-yielding coarse paddy.',
                    'sort_order' => 5,
                ],
                [
                    'name' => 'Sona Masoori',
                    'slug' => 'sona-masoori',
                    'description' => 'Lightweight, aromatic medium-grain rice largely grown in South India.',
                    'sort_order' => 6,
                ],
            ],
            'maize' => [
                [
                    'name' => 'Yellow Maize',
                    'slug' => 'yellow-maize',
                    'description' => 'Standard commercial yellow corn for poultry feed and starch processing.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Feed Grade Maize',
                    'slug' => 'feed-grade-maize',
                    'description' => 'Maize with specification tailored for livestock and cattle feed.',
                    'sort_order' => 2,
                ],
            ],
            'barley' => [
                [
                    'name' => 'Feed Barley',
                    'slug' => 'feed-barley',
                    'description' => 'Barley grain used for cattle and animal nutrition.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Malt Quality Barley',
                    'slug' => 'malt-barley',
                    'description' => 'Two-row & six-row barley suitable for brewing and malting industry.',
                    'sort_order' => 2,
                ],
            ],
            'chana' => [
                [
                    'name' => 'Desi Chana',
                    'slug' => 'desi-chana',
                    'description' => 'Standard brown gram traded in physical mandis and derivative markets.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Chana Kanta',
                    'slug' => 'chana-kanta',
                    'description' => 'Madhya Pradesh origin pointed desi chana benchmark quality.',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Kabuli Chana (Dollar)',
                    'slug' => 'kabuli-chana-dollar',
                    'description' => 'Large white chickpeas (42-44, 44-46 count) renowned as Dollar Chana.',
                    'sort_order' => 3,
                ],
            ],
            'tur-arhar' => [
                [
                    'name' => 'Desi White Tur',
                    'slug' => 'desi-white-tur',
                    'description' => 'Indigenous white pigeon pea (Gulbarga/Maharashtra origin).',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Red / Pink Tur',
                    'slug' => 'red-pink-tur',
                    'description' => 'Reddish pigeon pea from Marathwada and Karnataka region.',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Lemon Tur (Imported)',
                    'slug' => 'lemon-tur-imported',
                    'description' => 'Burma origin imported lemon tur.',
                    'sort_order' => 3,
                ],
            ],
            'moong' => [
                [
                    'name' => 'Shiny Green Moong',
                    'slug' => 'shiny-green-moong',
                    'description' => 'Polished shiny green mung bean for direct retail pack and sprouting.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Medium / Bold Moong',
                    'slug' => 'medium-bold-moong',
                    'description' => 'Large sized green mung beans for dal milling.',
                    'sort_order' => 2,
                ],
            ],
            'urad' => [
                [
                    'name' => 'FAQ Black Urad',
                    'slug' => 'faq-black-urad',
                    'description' => 'Fair Average Quality whole black gram.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Bold Black Urad',
                    'slug' => 'bold-black-urad',
                    'description' => 'Large size uniform black gram preferred by dal millers.',
                    'sort_order' => 2,
                ],
            ],
            'masoor' => [
                [
                    'name' => 'Desi Small Masoor',
                    'slug' => 'desi-small-masoor',
                    'description' => 'Local small red lentils from MP and UP mandis.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Imported Bold Masoor',
                    'slug' => 'imported-bold-masoor',
                    'description' => 'Canadian / Australian large crimson lentils.',
                    'sort_order' => 2,
                ],
            ],
            'soybean' => [
                [
                    'name' => 'Yellow Soybean (Plant Delivery)',
                    'slug' => 'yellow-soybean-plant-delivery',
                    'description' => 'Crushing plant delivery standard yellow soybean with maximum 2% moisture & 2% foreign matter.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Mandi Quality Soybean',
                    'slug' => 'mandi-quality-soybean',
                    'description' => 'Physical auction lot traded in agricultural produce market committees (APMC).',
                    'sort_order' => 2,
                ],
            ],
            'mustard-rapeseed' => [
                [
                    'name' => '42% Condition Mustard',
                    'slug' => 'mustard-42-condition',
                    'description' => 'Standard trading condition benchmark with 42% oil content basis.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Black Mustard (Rada / Raya)',
                    'slug' => 'black-mustard-raya',
                    'description' => 'Rajasthan and Haryana origin black mustard seed.',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Yellow Mustard (Peeli Sarson)',
                    'slug' => 'yellow-mustard',
                    'description' => 'High-value yellow mustard seed used for oil extraction and spice blends.',
                    'sort_order' => 3,
                ],
            ],
            'groundnut' => [
                [
                    'name' => 'Groundnut Pods (In-shell)',
                    'slug' => 'groundnut-pods-in-shell',
                    'description' => 'Raw harvested unshelled groundnut pods traded in mandis.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Groundnut Kernel / Dana (TJ / Bold)',
                    'slug' => 'groundnut-kernel-dana',
                    'description' => 'Shelled peanut kernels sorted by count for table and oil crushing.',
                    'sort_order' => 2,
                ],
            ],
            'refined-soy-oil' => [
                [
                    'name' => 'Kandla Port Refined Soy Oil',
                    'slug' => 'kandla-port-refined-soy-oil',
                    'description' => 'Port landed bulk refined soybean oil price benchmark (10 kg basis).',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Indore Delivery Soy Oil',
                    'slug' => 'indore-delivery-soy-oil',
                    'description' => 'Central India regional benchmark for edible soybean oil.',
                    'sort_order' => 2,
                ],
            ],
            'mustard-oil' => [
                [
                    'name' => 'Kachi Ghani Mustard Oil',
                    'slug' => 'kachi-ghani-mustard-oil',
                    'description' => 'Cold pressed strong pungency edible mustard oil.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Pakki Ghani Mustard Oil',
                    'slug' => 'pakki-ghani-mustard-oil',
                    'description' => 'Expeller extracted filtered mustard oil.',
                    'sort_order' => 2,
                ],
            ],
            'crude-palm-oil-cpo' => [
                [
                    'name' => 'Kandla CPO',
                    'slug' => 'kandla-cpo',
                    'description' => 'Crude palm oil delivery benchmark at Kandla port.',
                    'sort_order' => 1,
                ],
            ],
            'jeera-cumin' => [
                [
                    'name' => 'Unjha Quality Jeera',
                    'slug' => 'unjha-quality-jeera',
                    'description' => 'Asia biggest cumin trading hub benchmark quality.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Machine Clean Jeera',
                    'slug' => 'machine-clean-jeera',
                    'description' => '99% purity sorted and machine cleaned cumin seed.',
                    'sort_order' => 2,
                ],
            ],
            'turmeric' => [
                [
                    'name' => 'Nizamabad Finger Turmeric',
                    'slug' => 'nizamabad-finger-turmeric',
                    'description' => 'Telangana origin golden finger turmeric.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Salem Finger Turmeric',
                    'slug' => 'salem-finger-turmeric',
                    'description' => 'Tamil Nadu origin high curcumin content finger turmeric.',
                    'sort_order' => 2,
                ],
            ],
            'coriander' => [
                [
                    'name' => 'Badami Coriander',
                    'slug' => 'badami-coriander',
                    'description' => 'Commercial grade almond-colored whole coriander seed.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Eagle Coriander',
                    'slug' => 'eagle-coriander',
                    'description' => 'High quality greenish-brown whole coriander seed.',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Scooter / Green Coriander',
                    'slug' => 'scooter-green-coriander',
                    'description' => 'Parrot green premium quality whole coriander for retail packing.',
                    'sort_order' => 3,
                ],
            ],
            'almonds' => [
                [
                    'name' => 'California Almonds (Independence / Nonpareil)',
                    'slug' => 'california-almonds',
                    'description' => 'Imported sweet Californian kernel almonds.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Mamra Almonds',
                    'slug' => 'mamra-almonds',
                    'description' => 'Premium high oil content organic Iranian/Afghani almonds.',
                    'sort_order' => 2,
                ],
            ],
            'cashews' => [
                [
                    'name' => 'Cashew W-240',
                    'slug' => 'cashew-w240',
                    'description' => 'Large whole white cashew kernels (240 nuts per pound).',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Cashew W-320',
                    'slug' => 'cashew-w320',
                    'description' => 'Standard commercial size whole white cashew (320 nuts per pound).',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Cashew Tukda / Broken (Jhilli)',
                    'slug' => 'cashew-broken-tukda',
                    'description' => 'Broken cashew kernels for sweet and culinary preparation.',
                    'sort_order' => 3,
                ],
            ],
            'sugar' => [
                [
                    'name' => 'M-30 Grade Sugar',
                    'slug' => 'sugar-m30',
                    'description' => 'Medium grain white crystal refined sugar.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'S-30 Grade Sugar',
                    'slug' => 'sugar-s30',
                    'description' => 'Small grain white crystal sugar.',
                    'sort_order' => 2,
                ],
            ],
            'jaggery-gur' => [
                [
                    'name' => 'Kolhapur Gur (Bucket Shape)',
                    'slug' => 'kolhapur-jaggery',
                    'description' => 'Traditional yellow crystalline Kolhapur jaggery.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Muzaffarnagar Gur (Chaku / Laddu)',
                    'slug' => 'muzaffarnagar-jaggery',
                    'description' => 'North Indian benchmark quality jaggery.',
                    'sort_order' => 2,
                ],
            ],
            'soy-meal-doc' => [
                [
                    'name' => 'De-oiled Soy Cake / Meal (DOC)',
                    'slug' => 'de-oiled-soy-meal',
                    'description' => 'High protein (48% pro-fat) soy meal for poultry and animal nutrition.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Hi-Pro Soy Meal',
                    'slug' => 'hi-pro-soy-meal',
                    'description' => '50%+ protein premium export grade soy extraction.',
                    'sort_order' => 2,
                ],
            ],
            'cottonseed-cake' => [
                [
                    'name' => 'Kadi Quality Cottonseed Khal',
                    'slug' => 'kadi-cottonseed-cake',
                    'description' => 'Gujarat Kadi market benchmark quality cattle feed cake.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Akola Quality Cottonseed Khal',
                    'slug' => 'akola-cottonseed-cake',
                    'description' => 'Vidarbha Akola quality cottonseed oil cake.',
                    'sort_order' => 2,
                ],
            ],
            'cbot-soybean' => [
                [
                    'name' => 'CBOT Soybean Near Month',
                    'slug' => 'cbot-soybean-near-month',
                    'description' => 'Chicago Board of Trade active benchmark contract in USD cents/bushel.',
                    'sort_order' => 1,
                ],
            ],
            'bmd-crude-palm-oil' => [
                [
                    'name' => 'BMD Palm Oil Active Future (3rd Month)',
                    'slug' => 'bmd-cpo-active-future',
                    'description' => 'Bursa Malaysia Derivatives active 3rd month benchmark contract in MYR/Tonne.',
                    'sort_order' => 1,
                ],
            ],
        ];

        foreach ($catalog as $commoditySlug => $subcategories) {
            $commodity = Commodity::where('slug', $commoditySlug)->first();

            if (! $commodity) {
                continue;
            }

            foreach ($subcategories as $item) {
                CommoditySubcategory::updateOrCreate(
                    ['slug' => $item['slug']],
                    [
                        'commodity_id' => $commodity->id,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'sort_order' => $item['sort_order'] ?? 0,
                        'status' => true,
                    ]
                );
            }
        }
    }
}
