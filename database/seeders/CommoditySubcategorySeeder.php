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
                    'name_en' => 'Lokwan Wheat',
                    'name_hi' => 'लोकवान गेहूं',
                    'slug' => 'lokwan-wheat',
                    'description_en' => 'Popular semi-hard wheat variety widely traded across MP, Maharashtra, and Gujarat.',
                    'description_hi' => 'मध्य प्रदेश, महाराष्ट्र और गुजरात में व्यापक रूप से व्यापार की जाने वाली लोकप्रिय किस्म।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Sharbati Wheat',
                    'name_hi' => 'शरबती गेहूं',
                    'slug' => 'sharbati-wheat',
                    'description_en' => 'Premium quality golden grain wheat, famous for sweet and soft chapatis (Sehore Sharbati).',
                    'description_hi' => 'सीहोर शरबती के नाम से प्रसिद्ध प्रीमियम गुणवत्ता वाला सुनहरी गेहूं।',
                    'sort_order' => 2,
                ],
                [
                    'name_en' => 'Milling Quality Wheat',
                    'name_hi' => 'मिलिंग क्वालिटी गेहूं',
                    'slug' => 'milling-quality-wheat',
                    'description_en' => 'Standard commercial wheat procured for flour and maida roller mills.',
                    'description_hi' => 'आटा व मैदा रोलर मिलों के लिए वाणिज्यिक ग्रेड गेहूं।',
                    'sort_order' => 3,
                ],
                [
                    'name_en' => 'Tukdi Wheat',
                    'name_hi' => 'टुकड़ी गेहूं',
                    'slug' => 'tukdi-wheat',
                    'description_en' => 'Gujarat tukdi variety known for luster, hardness, and high protein.',
                    'description_hi' => 'गुजरात की प्रसिद्ध टुकड़ी वैरायटी, चमकदार और उच्च प्रोटीन युक्त।',
                    'sort_order' => 4,
                ],
                [
                    'name_en' => 'Malavraj (Durum Wheat)',
                    'name_hi' => 'मालवराज (ड्रम गेहूं)',
                    'slug' => 'malavraj-durum-wheat',
                    'description_en' => 'Hard durum wheat used extensively for pasta, semolina (suji), and vermicelli.',
                    'description_hi' => 'सूजी, पास्ता और सेवई निर्माण हेतु उपयोगी सख्त ड्रम गेहूं।',
                    'sort_order' => 5,
                ],
            ],
            'paddy-rice' => [
                [
                    'name_en' => 'Basmati 1121',
                    'name_hi' => 'बासमती 1121',
                    'slug' => 'basmati-1121',
                    'description_en' => 'Extra long grain premium export quality Basmati variety.',
                    'description_hi' => 'अतिरिक्त लंबे दाने वाली प्रीमियम निर्यात बासमती किस्म।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Basmati 1509',
                    'name_hi' => 'बासमती 1509',
                    'slug' => 'basmati-1509',
                    'description_en' => 'Early maturing aromatic long grain Basmati variety.',
                    'description_hi' => 'शीघ्र पकने वाली सुगंधित लंबे दाने की बासमती किस्म।',
                    'sort_order' => 2,
                ],
                [
                    'name_en' => 'Basmati 1718',
                    'name_hi' => 'बासमती 1718',
                    'slug' => 'basmati-1718',
                    'description_en' => 'Blight resistant improved successor of 1121 variety.',
                    'description_hi' => 'रोग प्रतिरोधी उन्नत बासमती 1121 का विकल्प।',
                    'sort_order' => 3,
                ],
                [
                    'name_en' => 'Sugandha Paddy',
                    'name_hi' => 'सुगंधा धान',
                    'slug' => 'sugandha-paddy',
                    'description_en' => 'Semi-aromatic medium long grain non-basmati rice variety.',
                    'description_hi' => 'मध्यम लंबी सुगंधित लोकप्रिय धान किस्म।',
                    'sort_order' => 4,
                ],
                [
                    'name_en' => 'PR 14 / Parmal',
                    'name_hi' => 'पीआर 14 / परमल',
                    'slug' => 'pr-14-parmal',
                    'description_en' => 'Standard non-basmati high-yielding coarse paddy.',
                    'description_hi' => 'दैनिक उपभोग व मिलिंग हेतु मानक गैर-बासमती धान।',
                    'sort_order' => 5,
                ],
                [
                    'name_en' => 'Sona Masoori',
                    'name_hi' => 'सोना मसूरी',
                    'slug' => 'sona-masoori',
                    'description_en' => 'Lightweight, aromatic medium-grain rice largely grown in South India.',
                    'description_hi' => 'दक्षिण भारत में लोकप्रिय हल्का और सुगंधित मध्यम दाना चावल।',
                    'sort_order' => 6,
                ],
            ],
            'maize' => [
                [
                    'name_en' => 'Yellow Maize',
                    'name_hi' => 'पीली मक्का',
                    'slug' => 'yellow-maize',
                    'description_en' => 'Standard commercial yellow corn for poultry feed and starch processing.',
                    'description_hi' => 'पोल्ट्री दाना और स्टार्च मिलों के लिए पीली मक्का।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Feed Grade Maize',
                    'name_hi' => 'फीड ग्रेड मक्का',
                    'slug' => 'feed-grade-maize',
                    'description_en' => 'Maize with specification tailored for livestock and cattle feed.',
                    'description_hi' => 'पशु आहार के लिए विशिष्ट गुणवत्ता वाली मक्का।',
                    'sort_order' => 2,
                ],
            ],
            'barley' => [
                [
                    'name_en' => 'Feed Barley',
                    'name_hi' => 'फीड जौ',
                    'slug' => 'feed-barley',
                    'description_en' => 'Barley grain used for cattle and animal nutrition.',
                    'description_hi' => 'पशु आहार हेतु प्रयुक्त जौ।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Malt Quality Barley',
                    'name_hi' => 'माल्ट क्वालिटी जौ',
                    'slug' => 'malt-barley',
                    'description_en' => 'Two-row & six-row barley suitable for brewing and malting industry.',
                    'description_hi' => 'माल्टिंग और ब्रूइंग उद्योगों के लिए उपयुक्त जौ।',
                    'sort_order' => 2,
                ],
            ],
            'chana' => [
                [
                    'name_en' => 'Desi Chana',
                    'name_hi' => 'देशी चना',
                    'slug' => 'desi-chana',
                    'description_en' => 'Standard brown gram traded in physical mandis and derivative markets.',
                    'description_hi' => 'मंडियों में सर्वाधिक व्यापार होने वाला भूरा देशी चना।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Chana Kanta',
                    'name_hi' => 'चना कांटा',
                    'slug' => 'chana-kanta',
                    'description_en' => 'Madhya Pradesh origin pointed desi chana benchmark quality.',
                    'description_hi' => 'मध्य प्रदेश कांटा क्वालिटी चना बेंचमार्क।',
                    'sort_order' => 2,
                ],
                [
                    'name_en' => 'Kabuli Chana (Dollar)',
                    'name_hi' => 'काबुली चना (डॉलर)',
                    'slug' => 'kabuli-chana-dollar',
                    'description_en' => 'Large white chickpeas (42-44, 44-46 count) renowned as Dollar Chana.',
                    'description_hi' => 'बड़ा सफेद काबुली चना (डॉलर चना)।',
                    'sort_order' => 3,
                ],
            ],
            'tur-arhar' => [
                [
                    'name_en' => 'Desi White Tur',
                    'name_hi' => 'देशी सफेद तुअर',
                    'slug' => 'desi-white-tur',
                    'description_en' => 'Indigenous white pigeon pea (Gulbarga/Maharashtra origin).',
                    'description_hi' => 'गुलबर्गा/महाराष्ट्र मूल की देशी सफेद तुअर।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Red / Pink Tur',
                    'name_hi' => 'लाल / गुलाबी तुअर',
                    'slug' => 'red-pink-tur',
                    'description_en' => 'Reddish pigeon pea from Marathwada and Karnataka region.',
                    'description_hi' => 'मराठवाड़ा और कर्नाटक क्षेत्र की लाल तुअर।',
                    'sort_order' => 2,
                ],
                [
                    'name_en' => 'Lemon Tur (Imported)',
                    'name_hi' => 'लेमन तुअर (आयातित)',
                    'slug' => 'lemon-tur-imported',
                    'description_en' => 'Burma origin imported lemon tur.',
                    'description_hi' => 'बर्मा से आयातित लेमन तुअर।',
                    'sort_order' => 3,
                ],
            ],
            'moong' => [
                [
                    'name_en' => 'Shiny Green Moong',
                    'name_hi' => 'चमकी हरी मूंग',
                    'slug' => 'shiny-green-moong',
                    'description_en' => 'Polished shiny green mung bean for direct retail pack and sprouting.',
                    'description_hi' => 'चमकदार दाने वाली उत्तम हरी मूंग।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Medium / Bold Moong',
                    'name_hi' => 'बोल्ड मूंग',
                    'slug' => 'medium-bold-moong',
                    'description_en' => 'Large sized green mung beans for dal milling.',
                    'description_hi' => 'दाल मिलिंग के लिए बड़े दाने वाली मूंग।',
                    'sort_order' => 2,
                ],
            ],
            'urad' => [
                [
                    'name_en' => 'FAQ Black Urad',
                    'name_hi' => 'एफएक्यू काला उड़द',
                    'slug' => 'faq-black-urad',
                    'description_en' => 'Fair Average Quality whole black gram.',
                    'description_hi' => 'मानक व्यापारिक एफएक्यू साबुत काला उड़द।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Bold Black Urad',
                    'name_hi' => 'बोल्ड काला उड़द',
                    'slug' => 'bold-black-urad',
                    'description_en' => 'Large size uniform black gram preferred by dal millers.',
                    'description_hi' => 'दाल मिलों की पहली पसंद बड़ा व एक समान दाना उड़द।',
                    'sort_order' => 2,
                ],
            ],
            'masoor' => [
                [
                    'name_en' => 'Desi Small Masoor',
                    'name_hi' => 'देशी छोटी मसूर',
                    'slug' => 'desi-small-masoor',
                    'description_en' => 'Local small red lentils from MP and UP mandis.',
                    'description_hi' => 'मध्य प्रदेश व यूपी मंडियों की देशी छोटी मसूर।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Imported Bold Masoor',
                    'name_hi' => 'कनाडियन बोल्ड मसूर',
                    'slug' => 'imported-bold-masoor',
                    'description_en' => 'Canadian / Australian large crimson lentils.',
                    'description_hi' => 'कनाडा व ऑस्ट्रेलिया से आयातित लाल मसूर।',
                    'sort_order' => 2,
                ],
            ],
            'soybean' => [
                [
                    'name_en' => 'Yellow Soybean (Plant Delivery)',
                    'name_hi' => 'पीला सोयाबीन (प्लांट डिलीवरी)',
                    'slug' => 'yellow-soybean-plant-delivery',
                    'description_en' => 'Crushing plant delivery standard yellow soybean with maximum 2% moisture & 2% foreign matter.',
                    'description_hi' => 'सॉल्वेंट प्लांट डिलीवरी मानक पीला सोयाबीन।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Mandi Quality Soybean',
                    'name_hi' => 'मंडी क्वालिटी सोयाबीन',
                    'slug' => 'mandi-quality-soybean',
                    'description_en' => 'Physical auction lot traded in agricultural produce market committees (APMC).',
                    'description_hi' => 'कृषि उपज मंडियों (APMC) में नीलाम होने वाला सोयाबीन।',
                    'sort_order' => 2,
                ],
            ],
            'mustard-rapeseed' => [
                [
                    'name_en' => '42% Condition Mustard',
                    'name_hi' => '42% कंडीशन सरसों',
                    'slug' => 'mustard-42-condition',
                    'description_en' => 'Standard trading condition benchmark with 42% oil content basis.',
                    'description_hi' => '42% तेल मात्रा आधार वाली मानक व्यापारिक सरसों।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Black Mustard (Rada / Raya)',
                    'name_hi' => 'काली सरसों (रायडा)',
                    'slug' => 'black-mustard-raya',
                    'description_en' => 'Rajasthan and Haryana origin black mustard seed.',
                    'description_hi' => 'राजस्थान और हरियाणा की प्रसिद्ध काली सरसों (रायडा)।',
                    'sort_order' => 2,
                ],
                [
                    'name_en' => 'Yellow Mustard (Peeli Sarson)',
                    'name_hi' => 'पीली सरसों',
                    'slug' => 'yellow-mustard',
                    'description_en' => 'High-value yellow mustard seed used for oil extraction and spice blends.',
                    'description_hi' => 'उच्च तेल मात्रा व मसालों में उपयोगी पीली सरसों।',
                    'sort_order' => 3,
                ],
            ],
            'groundnut' => [
                [
                    'name_en' => 'Groundnut Pods (In-shell)',
                    'name_hi' => 'मूंगफली फली',
                    'slug' => 'groundnut-pods-in-shell',
                    'description_en' => 'Raw harvested unshelled groundnut pods traded in mandis.',
                    'description_hi' => 'मंडियों में व्यापार होने वाली कच्ची साबुत मूंगफली फली।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Groundnut Kernel / Dana (TJ / Bold)',
                    'name_hi' => 'मूंगफली दाना (टीजे / बोल्ड)',
                    'slug' => 'groundnut-kernel-dana',
                    'description_en' => 'Shelled peanut kernels sorted by count for table and oil crushing.',
                    'description_hi' => 'काउंट अनुसार छांटा गया मूंगफली दाना।',
                    'sort_order' => 2,
                ],
            ],
            'refined-soy-oil' => [
                [
                    'name_en' => 'Kandla Port Refined Soy Oil',
                    'name_hi' => 'कांडला पोर्ट सोया तेल',
                    'slug' => 'kandla-port-refined-soy-oil',
                    'description_en' => 'Port landed bulk refined soybean oil price benchmark (10 kg basis).',
                    'description_hi' => 'कांडला पोर्ट आधारित थोक रिफाइंड सोया तेल (10 किग्रा आधार)।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Indore Delivery Soy Oil',
                    'name_hi' => 'इंदौर डिलीवरी सोया तेल',
                    'slug' => 'indore-delivery-soy-oil',
                    'description_en' => 'Central India regional benchmark for edible soybean oil.',
                    'description_hi' => 'मध्य भारत का प्रमुख खाद्य सोया तेल बेंचमार्क।',
                    'sort_order' => 2,
                ],
            ],
            'mustard-oil' => [
                [
                    'name_en' => 'Kachi Ghani Mustard Oil',
                    'name_hi' => 'कच्ची घानी सरसों तेल',
                    'slug' => 'kachi-ghani-mustard-oil',
                    'description_en' => 'Cold pressed strong pungency edible mustard oil.',
                    'description_hi' => 'तीखे स्वाद व महक वाला पारंपरिक कच्ची घानी सरसों तेल।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Pakki Ghani Mustard Oil',
                    'name_hi' => 'पक्की घानी सरसों तेल',
                    'slug' => 'pakki-ghani-mustard-oil',
                    'description_en' => 'Expeller extracted filtered mustard oil.',
                    'description_hi' => 'एक्सपेलर निष्कर्षित फ़िल्टर सरसों तेल।',
                    'sort_order' => 2,
                ],
            ],
            'crude-palm-oil-cpo' => [
                [
                    'name_en' => 'Kandla CPO',
                    'name_hi' => 'कांडला सीपीओ',
                    'slug' => 'kandla-cpo',
                    'description_en' => 'Crude palm oil delivery benchmark at Kandla port.',
                    'description_hi' => 'कांडला पोर्ट कच्चा पाम तेल डिलीवरी।',
                    'sort_order' => 1,
                ],
            ],
            'jeera-cumin' => [
                [
                    'name_en' => 'Unjha Quality Jeera',
                    'name_hi' => 'ऊंझा क्वालिटी जीरा',
                    'slug' => 'unjha-quality-jeera',
                    'description_en' => 'Asia biggest cumin trading hub benchmark quality.',
                    'description_hi' => 'एशिया के सबसे बड़े जीरा केंद्र ऊंझा की बेंचमार्क क्वालिटी।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Machine Clean Jeera',
                    'name_hi' => 'मशीन क्लीन जीरा',
                    'slug' => 'machine-clean-jeera',
                    'description_en' => '99% purity sorted and machine cleaned cumin seed.',
                    'description_hi' => '99% शुद्धता वाला मशीन क्लीन जीरा।',
                    'sort_order' => 2,
                ],
            ],
            'turmeric' => [
                [
                    'name_en' => 'Nizamabad Finger Turmeric',
                    'name_hi' => 'निजामाबाद फिंगर हल्दी',
                    'slug' => 'nizamabad-finger-turmeric',
                    'description_en' => 'Telangana origin golden finger turmeric.',
                    'description_hi' => 'तेलंगाना निजामाबाद की सुनहरी फिंगर हल्दी।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Salem Finger Turmeric',
                    'name_hi' => 'सेलम फिंगर हल्दी',
                    'slug' => 'salem-finger-turmeric',
                    'description_en' => 'Tamil Nadu origin high curcumin content finger turmeric.',
                    'description_hi' => 'उच्च करक्यूमिन युक्त तमिलनाडु की सेलम हल्दी।',
                    'sort_order' => 2,
                ],
            ],
            'coriander' => [
                [
                    'name_en' => 'Badami Coriander',
                    'name_hi' => 'बादामी धनिया',
                    'slug' => 'badami-coriander',
                    'description_en' => 'Commercial grade almond-colored whole coriander seed.',
                    'description_hi' => 'वाणिज्यिक बादामी रंग का साबुत धनिया दाना।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Eagle Coriander',
                    'name_hi' => 'ईगल धनिया',
                    'slug' => 'eagle-coriander',
                    'description_en' => 'High quality greenish-brown whole coriander seed.',
                    'description_hi' => 'प्रीमियम क्वालिटी ईगल ग्रेड धनिया।',
                    'sort_order' => 2,
                ],
                [
                    'name_en' => 'Scooter / Green Coriander',
                    'name_hi' => 'स्कूटर / हरा धनिया',
                    'slug' => 'scooter-green-coriander',
                    'description_en' => 'Parrot green premium quality whole coriander for retail packing.',
                    'description_hi' => 'तोते जैसा हरा प्रीमियम साबुत धनिया।',
                    'sort_order' => 3,
                ],
            ],
            'almonds' => [
                [
                    'name_en' => 'California Almonds (Independence / Nonpareil)',
                    'name_hi' => 'कैलिफोर्निया बादाम',
                    'slug' => 'california-almonds',
                    'description_en' => 'Imported sweet Californian kernel almonds.',
                    'description_hi' => 'आयातित मीठा कैलिफोर्निया बादाम गिरी।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Mamra Almonds',
                    'name_hi' => 'मामरा बादाम',
                    'slug' => 'mamra-almonds',
                    'description_en' => 'Premium high oil content organic Iranian/Afghani almonds.',
                    'description_hi' => 'उच्च तेल व पौष्टिकता वाला प्रीमियम मामरा बादाम।',
                    'sort_order' => 2,
                ],
            ],
            'cashews' => [
                [
                    'name_en' => 'Cashew W-240',
                    'name_hi' => 'काजू W-240',
                    'slug' => 'cashew-w240',
                    'description_en' => 'Large whole white cashew kernels (240 nuts per pound).',
                    'description_hi' => 'बड़ा साबुत सफेद काजू (240 दाने प्रति पाउंड)।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Cashew W-320',
                    'name_hi' => 'काजू W-320',
                    'slug' => 'cashew-w320',
                    'description_en' => 'Standard commercial size whole white cashew (320 nuts per pound).',
                    'description_hi' => 'मानक व्यावसायिक साबुत काजू (320 दाने प्रति पाउंड)।',
                    'sort_order' => 2,
                ],
                [
                    'name_en' => 'Cashew Tukda / Broken (Jhilli)',
                    'name_hi' => 'काजू टुकड़ा (झिल्ली/टुकड़ा)',
                    'slug' => 'cashew-broken-tukda',
                    'description_en' => 'Broken cashew kernels for sweet and culinary preparation.',
                    'description_hi' => 'मिठाई व खाद्य निर्माण हेतु काजू टुकड़ा।',
                    'sort_order' => 3,
                ],
            ],
            'sugar' => [
                [
                    'name_en' => 'M-30 Grade Sugar',
                    'name_hi' => 'एम-30 ग्रेड चीनी',
                    'slug' => 'sugar-m30',
                    'description_en' => 'Medium grain white crystal refined sugar.',
                    'description_hi' => 'मध्यम दाने वाली सफेद क्रिस्टल चीनी।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'S-30 Grade Sugar',
                    'name_hi' => 'एस-30 ग्रेड चीनी',
                    'slug' => 'sugar-s30',
                    'description_en' => 'Small grain white crystal sugar.',
                    'description_hi' => 'छोटे दाने की सफेद क्रिस्टल चीनी।',
                    'sort_order' => 2,
                ],
            ],
            'jaggery-gur' => [
                [
                    'name_en' => 'Kolhapur Gur (Bucket Shape)',
                    'name_hi' => 'कोल्हापुरी गुड़',
                    'slug' => 'kolhapur-jaggery',
                    'description_en' => 'Traditional yellow crystalline Kolhapur jaggery.',
                    'description_hi' => 'पारंपरिक पीला रवेदार कोल्हापुरी गुड़।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Muzaffarnagar Gur (Chaku / Laddu)',
                    'name_hi' => 'मुजफ्फरनगर गुड़',
                    'slug' => 'muzaffarnagar-jaggery',
                    'description_en' => 'North Indian benchmark quality jaggery.',
                    'description_hi' => 'उत्तर भारत का प्रसिद्ध मुजफ्फरनगर गुड़ (चाकू/लड्डू)।',
                    'sort_order' => 2,
                ],
            ],
            'soy-meal-doc' => [
                [
                    'name_en' => 'De-oiled Soy Cake / Meal (DOC)',
                    'name_hi' => 'सोया डीओसी (खली)',
                    'slug' => 'de-oiled-soy-meal',
                    'description_en' => 'High protein (48% pro-fat) soy meal for poultry and animal nutrition.',
                    'description_hi' => 'पोल्ट्री व पशु आहार के लिए 48% प्रोटीन युक्त सोया खली।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Hi-Pro Soy Meal',
                    'name_hi' => 'हाई-प्रो सोया मील',
                    'slug' => 'hi-pro-soy-meal',
                    'description_en' => '50%+ protein premium export grade soy extraction.',
                    'description_hi' => '50%+ प्रोटीन युक्त प्रीमियम निर्यात ग्रेड सोया मील।',
                    'sort_order' => 2,
                ],
            ],
            'cottonseed-cake' => [
                [
                    'name_en' => 'Kadi Quality Cottonseed Khal',
                    'name_hi' => 'कड़ी बिनौला खल',
                    'slug' => 'kadi-cottonseed-cake',
                    'description_en' => 'Gujarat Kadi market benchmark quality cattle feed cake.',
                    'description_hi' => 'गुजरात कड़ी मार्केट की बेंचमार्क बिनौला खल।',
                    'sort_order' => 1,
                ],
                [
                    'name_en' => 'Akola Quality Cottonseed Khal',
                    'name_hi' => 'अकोला बिनौला खल',
                    'slug' => 'akola-cottonseed-cake',
                    'description_en' => 'Vidarbha Akola quality cottonseed oil cake.',
                    'description_hi' => 'विदर्भ अकोला क्वालिटी बिनौला खल।',
                    'sort_order' => 2,
                ],
            ],
            'cbot-soybean' => [
                [
                    'name_en' => 'CBOT Soybean Near Month',
                    'name_hi' => 'सीबीओटी सोयाबीन चालू माह वायदा',
                    'slug' => 'cbot-soybean-near-month',
                    'description_en' => 'Chicago Board of Trade active benchmark contract in USD cents/bushel.',
                    'description_hi' => 'शिकागो बोर्ड ऑफ ट्रेड एक्टिव सोयाबीन अनुबंध (सेंट्स/बुशेल)।',
                    'sort_order' => 1,
                ],
            ],
            'bmd-crude-palm-oil' => [
                [
                    'name_en' => 'BMD Palm Oil Active Future (3rd Month)',
                    'name_hi' => 'बीएमडी पाम तेल 3रा माह वायदा',
                    'slug' => 'bmd-cpo-active-future',
                    'description_en' => 'Bursa Malaysia Derivatives active 3rd month benchmark contract in MYR/Tonne.',
                    'description_hi' => 'बुर्सा मलेशिया डेरिवेटिव्स सक्रिय तृतीय माह पाम तेल अनुबंध (मलेशियाई रिंगित/टन)।',
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
                        'name_en' => $item['name_en'],
                        'name_hi' => $item['name_hi'] ?? null,
                        'description_en' => $item['description_en'] ?? null,
                        'description_hi' => $item['description_hi'] ?? null,
                        'sort_order' => $item['sort_order'] ?? 0,
                        'status' => true,
                    ]
                );
            }
        }
    }
}
