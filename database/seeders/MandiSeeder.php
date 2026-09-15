<?php

namespace Database\Seeders;

use App\Enums\MarketType;
use App\Models\District;
use App\Models\Mandi;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MandiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Comprehensive Master list of major APMC Mandis across Indian states.
     */
    public function run(): void
    {
        $statesMap = State::all()->keyBy('code');

        $mandisData = [
            // Maharashtra (MH)
            'MH' => [
                'Nagpur' => [
                    ['name' => 'Nagpur APMC Mandi (Kalamna)', 'code' => 'NGP-APMC', 'type' => MarketType::APMC, 'address' => 'Kalamna Market Yard, Nagpur', 'pincode' => '440035'],
                    ['name' => 'Katol APMC Sub Yard', 'code' => 'KTL-SUB', 'type' => MarketType::SUB_YARD, 'address' => 'Katol APMC Sub Yard, Nagpur', 'pincode' => '441302'],
                    ['name' => 'Umred APMC Mandi', 'code' => 'UMR-APMC', 'type' => MarketType::APMC, 'address' => 'Umred APMC Market Yard, Nagpur', 'pincode' => '441203'],
                    ['name' => 'Saoner APMC Mandi', 'code' => 'SNR-APMC', 'type' => MarketType::APMC, 'address' => 'Saoner Market Yard, Saoner, Nagpur', 'pincode' => '441107'],
                ],
                'Akola' => [
                    ['name' => 'Akola APMC Mandi', 'code' => 'AKL-APMC', 'type' => MarketType::APMC, 'address' => 'Cotton Market Yard, Akola', 'pincode' => '444001'],
                    ['name' => 'Akot APMC Mandi', 'code' => 'AKT-APMC', 'type' => MarketType::APMC, 'address' => 'Akot Cotton Yard, Akola', 'pincode' => '444101'],
                    ['name' => 'Murtizapur APMC Mandi', 'code' => 'MZP-APMC', 'type' => MarketType::APMC, 'address' => 'Murtizapur Grain Yard, Akola', 'pincode' => '444107'],
                ],
                'Latur' => [
                    ['name' => 'Latur APMC Principal Yard', 'code' => 'LTR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Market Yard, Latur', 'pincode' => '413512'],
                    ['name' => 'Ahmedpur APMC Mandi', 'code' => 'ADP-APMC', 'type' => MarketType::APMC, 'address' => 'Ahmedpur Yard, Latur', 'pincode' => '413515'],
                    ['name' => 'Udgir APMC Mandi', 'code' => 'UDG-APMC', 'type' => MarketType::APMC, 'address' => 'Udgir Market Yard, Latur', 'pincode' => '413517'],
                ],
                'Nashik' => [
                    ['name' => 'Lasalgaon APMC (Onion Hub)', 'code' => 'LSG-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Lasalgaon Onion Yard, Nashik', 'pincode' => '422306'],
                    ['name' => 'Pimpalgaon Baswant APMC', 'code' => 'PMP-APMC', 'type' => MarketType::APMC, 'address' => 'Pimpalgaon Tomato & Onion Yard, Nashik', 'pincode' => '422209'],
                    ['name' => 'Nashik APMC Main Yard', 'code' => 'NSK-APMC', 'type' => MarketType::APMC, 'address' => 'Peth Road, Panchavati, Nashik', 'pincode' => '422003'],
                    ['name' => 'Yeola APMC Mandi', 'code' => 'YLA-APMC', 'type' => MarketType::APMC, 'address' => 'Yeola APMC Yard, Nashik', 'pincode' => '423401'],
                ],
                'Pune' => [
                    ['name' => 'Pune APMC (Gultekdi Market Yard)', 'code' => 'PUN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Gultekdi Market Yard, Pune', 'pincode' => '411037'],
                    ['name' => 'Baramati APMC Mandi', 'code' => 'BRM-APMC', 'type' => MarketType::APMC, 'address' => 'Baramati Market Yard, Pune', 'pincode' => '413102'],
                    ['name' => 'Manchar APMC Mandi', 'code' => 'MCR-APMC', 'type' => MarketType::APMC, 'address' => 'Manchar Market Yard, Pune', 'pincode' => '410503'],
                ],
                'Ahmednagar' => [
                    ['name' => 'Ahmednagar APMC Mandi', 'code' => 'AHM-APMC', 'type' => MarketType::APMC, 'address' => 'Nepti Market Yard, Ahmednagar', 'pincode' => '414001'],
                    ['name' => 'Rahuri APMC Mandi', 'code' => 'RHR-APMC', 'type' => MarketType::APMC, 'address' => 'Rahuri Market Yard, Ahmednagar', 'pincode' => '413705'],
                    ['name' => 'Shrirampur APMC Mandi', 'code' => 'SRP-APMC', 'type' => MarketType::APMC, 'address' => 'Shrirampur Grain Yard, Ahmednagar', 'pincode' => '413709'],
                ],
                'Amravati' => [
                    ['name' => 'Amravati APMC Principal Yard', 'code' => 'AMR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Cotton & Grain Yard, Amravati', 'pincode' => '444601'],
                    ['name' => 'Achalpur APMC Mandi', 'code' => 'ACH-APMC', 'type' => MarketType::APMC, 'address' => 'Achalpur Market Yard, Amravati', 'pincode' => '444806'],
                ],
                'Jalgaon' => [
                    ['name' => 'Jalgaon APMC Mandi', 'code' => 'JAL-APMC', 'type' => MarketType::APMC, 'address' => 'Krishi Utpanna Bajar Samiti, Jalgaon', 'pincode' => '425001'],
                    ['name' => 'Raver APMC (Banana Hub)', 'code' => 'RVR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Raver Banana Yard, Jalgaon', 'pincode' => '425508'],
                ],
                'Kolhapur' => [
                    ['name' => 'Kolhapur APMC (Jaggery Market)', 'code' => 'KLP-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Shahu Market Yard, Kolhapur', 'pincode' => '416005'],
                ],
                'Solapur' => [
                    ['name' => 'Solapur APMC Principal Yard', 'code' => 'SLP-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Siddheshwar Market Yard, Solapur', 'pincode' => '413002'],
                ],
                'Sangli' => [
                    ['name' => 'Sangli APMC (Turmeric & Raisin Hub)', 'code' => 'SNG-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Market Yard, Sangli', 'pincode' => '416416'],
                ],
            ],

            // Madhya Pradesh (MP)
            'MP' => [
                'Indore' => [
                    ['name' => 'Indore APMC Mandi (Chhavani)', 'code' => 'IND-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Chhavani Krishi Upaj Mandi, Indore', 'pincode' => '452001'],
                    ['name' => 'Sanwer APMC Sub-Yard', 'code' => 'SNW-SUB', 'type' => MarketType::SUB_YARD, 'address' => 'Sanwer Krishi Upaj Mandi, Indore', 'pincode' => '453551'],
                    ['name' => 'Depalpur APMC Mandi', 'code' => 'DPL-APMC', 'type' => MarketType::APMC, 'address' => 'Depalpur Market Yard, Indore', 'pincode' => '453115'],
                ],
                'Ujjain' => [
                    ['name' => 'Ujjain Krishi Upaj Mandi (Chimanganj)', 'code' => 'UJN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Chimanganj Mandi, Ujjain', 'pincode' => '456006'],
                    ['name' => 'Nagda APMC Mandi', 'code' => 'NGD-APMC', 'type' => MarketType::APMC, 'address' => 'Nagda Krishi Mandi, Ujjain', 'pincode' => '456335'],
                    ['name' => 'Mahidpur APMC Mandi', 'code' => 'MPR-APMC', 'type' => MarketType::APMC, 'address' => 'Mahidpur Yard, Ujjain', 'pincode' => '456443'],
                ],
                'Neemuch' => [
                    ['name' => 'Neemuch Krishi Upaj Mandi (Spices & Herbs Hub)', 'code' => 'NMH-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Krishi Upaj Mandi Samiti, Neemuch', 'pincode' => '458441'],
                    ['name' => 'Manasa APMC Mandi', 'code' => 'MNS-APMC', 'type' => MarketType::APMC, 'address' => 'Manasa Market Yard, Neemuch', 'pincode' => '458110'],
                    ['name' => 'Jawad APMC Mandi', 'code' => 'JWD-APMC', 'type' => MarketType::APMC, 'address' => 'Jawad Mandi Yard, Neemuch', 'pincode' => '458330'],
                ],
                'Mandsaur' => [
                    ['name' => 'Mandsaur APMC Mandi (Garlic & Soy Hub)', 'code' => 'MDR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Mandsaur Krishi Upaj Mandi, Mandsaur', 'pincode' => '458001'],
                    ['name' => 'Piplia Mandi APMC', 'code' => 'PPL-APMC', 'type' => MarketType::APMC, 'address' => 'Piplia Mandi Yard, Mandsaur', 'pincode' => '458664'],
                ],
                'Dewas' => [
                    ['name' => 'Dewas APMC Mandi', 'code' => 'DWS-APMC', 'type' => MarketType::APMC, 'address' => 'Dewas Krishi Mandi, Dewas', 'pincode' => '455001'],
                    ['name' => 'Sonkatch APMC Mandi', 'code' => 'SNK-APMC', 'type' => MarketType::APMC, 'address' => 'Sonkatch Yard, Dewas', 'pincode' => '455118'],
                ],
                'Ratlam' => [
                    ['name' => 'Ratlam APMC Mandi (Namkeen & Grain)', 'code' => 'RTL-APMC', 'type' => MarketType::APMC, 'address' => 'Mahow Neemuch Road, Ratlam', 'pincode' => '457001'],
                    ['name' => 'Jaora APMC Mandi', 'code' => 'JRA-APMC', 'type' => MarketType::APMC, 'address' => 'Jaora Market Yard, Ratlam', 'pincode' => '457226'],
                ],
                'Khargone' => [
                    ['name' => 'Khargone APMC (Cotton & Chilli Hub)', 'code' => 'KRG-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Khargone Market Yard, Khargone', 'pincode' => '451001'],
                    ['name' => 'Bediya APMC Chilli Mandi', 'code' => 'BED-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Bediya Red Chilli Yard, Khargone', 'pincode' => '451113'],
                ],
                'Harda' => [
                    ['name' => 'Harda APMC Mandi', 'code' => 'HRD-APMC', 'type' => MarketType::APMC, 'address' => 'Harda Krishi Upaj Mandi, Harda', 'pincode' => '461331'],
                ],
                'Gwalior' => [
                    ['name' => 'Gwalior APMC (Lashkar Mandi)', 'code' => 'GWL-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Lashkar Krishi Mandi, Gwalior', 'pincode' => '474001'],
                ],
                'Bhopal' => [
                    ['name' => 'Bhopal APMC (Karond Mandi)', 'code' => 'BPL-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Karond Mandi, Bhopal', 'pincode' => '462038'],
                ],
            ],

            // Gujarat (GJ)
            'GJ' => [
                'Rajkot' => [
                    ['name' => 'Rajkot APMC Bedi Yard', 'code' => 'RJT-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Bedi Marketing Yard, Rajkot', 'pincode' => '360003'],
                    ['name' => 'Gondal APMC (Groundnut & Chilli Hub)', 'code' => 'GDL-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Gondal Marketing Yard, Rajkot', 'pincode' => '360311'],
                    ['name' => 'Jasdan APMC Mandi', 'code' => 'JSD-APMC', 'type' => MarketType::APMC, 'address' => 'Jasdan Market Yard, Rajkot', 'pincode' => '360050'],
                ],
                'Patan' => [
                    ['name' => 'Unjha APMC (World Jeera & Isabgol Hub)', 'code' => 'UNJ-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'APMC Market Yard, Unjha, Patan', 'pincode' => '384170'],
                    ['name' => 'Patan APMC Mandi', 'code' => 'PTN-APMC', 'type' => MarketType::APMC, 'address' => 'Patan Marketing Yard, Patan', 'pincode' => '384265'],
                    ['name' => 'Sidhpur APMC Mandi', 'code' => 'SDP-APMC', 'type' => MarketType::APMC, 'address' => 'Sidhpur Market Yard, Patan', 'pincode' => '384151'],
                ],
                'Ahmedabad' => [
                    ['name' => 'Ahmedabad APMC (Jamalpur/Vasna)', 'code' => 'AMD-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Vasna APMC Market, Ahmedabad', 'pincode' => '380007'],
                    ['name' => 'Sanand APMC Mandi', 'code' => 'SND-APMC', 'type' => MarketType::APMC, 'address' => 'Sanand Market Yard, Ahmedabad', 'pincode' => '382110'],
                    ['name' => 'Mandal APMC Mandi', 'code' => 'MND-APMC', 'type' => MarketType::APMC, 'address' => 'Mandal Market Yard, Ahmedabad', 'pincode' => '382130'],
                ],
                'Surat' => [
                    ['name' => 'Surat APMC (Sardar Market)', 'code' => 'SRT-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Sardar Market, Sahara Darwaja, Surat', 'pincode' => '395010'],
                ],
                'Amreli' => [
                    ['name' => 'Amreli APMC Mandi', 'code' => 'AMR-APMC', 'type' => MarketType::APMC, 'address' => 'Amreli Market Yard, Amreli', 'pincode' => '365601'],
                    ['name' => 'Savarkundla APMC Mandi', 'code' => 'SVK-APMC', 'type' => MarketType::APMC, 'address' => 'Savarkundla Marketing Yard, Amreli', 'pincode' => '364515'],
                ],
                'Junagadh' => [
                    ['name' => 'Junagadh APMC Mandi', 'code' => 'JND-APMC', 'type' => MarketType::APMC, 'address' => 'Junagadh Market Yard, Junagadh', 'pincode' => '362001'],
                    ['name' => 'Keshod APMC Mandi', 'code' => 'KSD-APMC', 'type' => MarketType::APMC, 'address' => 'Keshod Yard, Junagadh', 'pincode' => '362220'],
                ],
                'Banaskantha' => [
                    ['name' => 'Deesa APMC (Potato Capital)', 'code' => 'DSA-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Deesa Potato & Grain Yard, Banaskantha', 'pincode' => '385535'],
                    ['name' => 'Tharad APMC Mandi', 'code' => 'TRD-APMC', 'type' => MarketType::APMC, 'address' => 'Tharad Market Yard, Banaskantha', 'pincode' => '385565'],
                ],
            ],

            // Rajasthan (RJ)
            'RJ' => [
                'Kota' => [
                    ['name' => 'Bhamashah Krishi Upaj Mandi Kota', 'code' => 'KTA-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Bhamashah Mandi, Anantpura, Kota', 'pincode' => '324005'],
                    ['name' => 'Ramganj Mandi (Coriander Hub)', 'code' => 'RGM-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Ramganj Mandi Yard, Kota', 'pincode' => '326519'],
                ],
                'Jaipur' => [
                    ['name' => 'Jaipur APMC (Muhana Mandi)', 'code' => 'JPR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Muhana Terminal Market, Jaipur', 'pincode' => '302029'],
                    ['name' => 'Kukas APMC Yard', 'code' => 'KKS-APMC', 'type' => MarketType::APMC, 'address' => 'Kukas Market Yard, Jaipur', 'pincode' => '302028'],
                    ['name' => 'Chomu APMC Mandi', 'code' => 'CHM-APMC', 'type' => MarketType::APMC, 'address' => 'Chomu Krishi Upaj Mandi, Jaipur', 'pincode' => '303702'],
                ],
                'Jodhpur' => [
                    ['name' => 'Jodhpur APMC (Bhagat Ki Kothi)', 'code' => 'JDH-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Bhagat Ki Kothi Mandi, Jodhpur', 'pincode' => '342005'],
                    ['name' => 'Piparcity APMC Mandi', 'code' => 'PPR-APMC', 'type' => MarketType::APMC, 'address' => 'Piparcity Yard, Jodhpur', 'pincode' => '342601'],
                ],
                'Bikaner' => [
                    ['name' => 'Bikaner Krishi Upaj Mandi (Moth & Guar Hub)', 'code' => 'BKN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Grain Market, Bikaner', 'pincode' => '334001'],
                    ['name' => 'Nokha APMC Mandi', 'code' => 'NKH-APMC', 'type' => MarketType::APMC, 'address' => 'Nokha Market Yard, Bikaner', 'pincode' => '334803'],
                ],
                'Sri Ganganagar' => [
                    ['name' => 'Sri Ganganagar Grain Mandi', 'code' => 'SGN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'New Dhan Mandi, Sri Ganganagar', 'pincode' => '335001'],
                    ['name' => 'Suratgarh APMC Mandi', 'code' => 'SRT-RJ-APMC', 'type' => MarketType::APMC, 'address' => 'Suratgarh Dhan Mandi, Sri Ganganagar', 'pincode' => '335804'],
                ],
                'Nagaur' => [
                    ['name' => 'Merta City APMC (Jeera & Methi Hub)', 'code' => 'MRT-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Merta City Market Yard, Nagaur', 'pincode' => '341510'],
                    ['name' => 'Nagaur Krishi Upaj Mandi', 'code' => 'NGR-APMC', 'type' => MarketType::APMC, 'address' => 'Nagaur Grain Yard, Nagaur', 'pincode' => '341001'],
                ],
                'Baran' => [
                    ['name' => 'Baran APMC Mandi (Garlic Hub)', 'code' => 'BRN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Baran Krishi Upaj Mandi, Baran', 'pincode' => '325205'],
                ],
            ],

            // Uttar Pradesh (UP)
            'UP' => [
                'Kanpur Nagar' => [
                    ['name' => 'Kanpur APMC (Naubasta Mandi)', 'code' => 'KNP-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Naubasta Mandi Samiti, Kanpur', 'pincode' => '208021'],
                    ['name' => 'Collectorganj Mandi', 'code' => 'CLG-APMC', 'type' => MarketType::APMC, 'address' => 'Collectorganj Wholesale Yard, Kanpur', 'pincode' => '208001'],
                ],
                'Lucknow' => [
                    ['name' => 'Lucknow APMC (Dubagga Mandi)', 'code' => 'LKO-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Dubagga Mandi Samiti, Lucknow', 'pincode' => '226003'],
                    ['name' => 'Sitapur Road Mandi Samiti', 'code' => 'STR-APMC', 'type' => MarketType::APMC, 'address' => 'Naveen Mandi Sthal, Sitapur Road, Lucknow', 'pincode' => '226020'],
                ],
                'Agra' => [
                    ['name' => 'Agra APMC (Sikandra Mandi)', 'code' => 'AGR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Sikandra Mandi Samiti, Agra', 'pincode' => '282007'],
                ],
                'Prayagraj' => [
                    ['name' => 'Prayagraj APMC (Mundera Mandi)', 'code' => 'PRY-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Mundera Mandi Samiti, Prayagraj', 'pincode' => '211011'],
                ],
                'Muzaffarnagar' => [
                    ['name' => 'Muzaffarnagar APMC (Jaggery/Gur Capital)', 'code' => 'MZF-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Naveen Mandi Sthal, Muzaffarnagar', 'pincode' => '251001'],
                ],
                'Bareilly' => [
                    ['name' => 'Bareilly APMC (Delapeer Mandi)', 'code' => 'BRL-APMC', 'type' => MarketType::APMC, 'address' => 'Delapeer Mandi Samiti, Bareilly', 'pincode' => '243122'],
                ],
                'Hathras' => [
                    ['name' => 'Hathras APMC Mandi (Asafoetida/Hing Hub)', 'code' => 'HTR-APMC', 'type' => MarketType::APMC, 'address' => 'Hathras Mandi Samiti, Hathras', 'pincode' => '204101'],
                ],
            ],

            // Punjab (PB)
            'PB' => [
                'Ludhiana' => [
                    ['name' => 'Ludhiana APMC (Gill Road Dana Mandi)', 'code' => 'LDH-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Dana Mandi, Gill Road, Ludhiana', 'pincode' => '141003'],
                    ['name' => 'Khanna APMC (Asia Largest Grain Mandi)', 'code' => 'KHN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Asia Largest Grain Market, Khanna, Ludhiana', 'pincode' => '141401'],
                    ['name' => 'Jagraon APMC Mandi', 'code' => 'JGR-APMC', 'type' => MarketType::APMC, 'address' => 'Jagraon Dana Mandi, Ludhiana', 'pincode' => '142026'],
                ],
                'Amritsar' => [
                    ['name' => 'Amritsar APMC (Bhagtanwala Mandi)', 'code' => 'ASR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Bhagtanwala Dana Mandi, Amritsar', 'pincode' => '143001'],
                    ['name' => 'Rayya APMC Mandi', 'code' => 'RYA-APMC', 'type' => MarketType::APMC, 'address' => 'Rayya Grain Market, Amritsar', 'pincode' => '143112'],
                ],
                'Bathinda' => [
                    ['name' => 'Bathinda APMC Mandi', 'code' => 'BTI-APMC', 'type' => MarketType::APMC, 'address' => 'New Grain Market, Bathinda', 'pincode' => '151001'],
                    ['name' => 'Rampura Phul APMC Mandi', 'code' => 'RMP-PB-APMC', 'type' => MarketType::APMC, 'address' => 'Rampura Phul Dana Mandi, Bathinda', 'pincode' => '151103'],
                ],
                'Patiala' => [
                    ['name' => 'Patiala APMC (Sirhind Road Mandi)', 'code' => 'PTL-APMC', 'type' => MarketType::APMC, 'address' => 'Sirhind Road Dana Mandi, Patiala', 'pincode' => '147001'],
                    ['name' => 'Nabha APMC Mandi', 'code' => 'NBH-APMC', 'type' => MarketType::APMC, 'address' => 'Nabha Dana Mandi, Patiala', 'pincode' => '147201'],
                ],
            ],

            // Haryana (HR)
            'HR' => [
                'Karnal' => [
                    ['name' => 'Karnal APMC (Basmati Rice Capital)', 'code' => 'KRN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'New Grain Market, Karnal', 'pincode' => '132001'],
                    ['name' => 'Gharaunda APMC Mandi', 'code' => 'GHR-APMC', 'type' => MarketType::APMC, 'address' => 'Gharaunda Grain Yard, Karnal', 'pincode' => '132114'],
                    ['name' => 'Taraori APMC Rice Mandi', 'code' => 'TRR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Taraori Basmati Market, Karnal', 'pincode' => '132116'],
                ],
                'Sirsa' => [
                    ['name' => 'Sirsa APMC Grain & Cotton Mandi', 'code' => 'SRS-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'New Anaj Mandi, Sirsa', 'pincode' => '125055'],
                    ['name' => 'Ellenabad APMC Mandi', 'code' => 'ELN-APMC', 'type' => MarketType::APMC, 'address' => 'Ellenabad Grain Market, Sirsa', 'pincode' => '125102'],
                ],
                'Hisar' => [
                    ['name' => 'Hisar APMC Mandi', 'code' => 'HSR-APMC', 'type' => MarketType::APMC, 'address' => 'New Grain Market, Hisar', 'pincode' => '125001'],
                    ['name' => 'Hansi APMC Mandi', 'code' => 'HNS-APMC', 'type' => MarketType::APMC, 'address' => 'Hansi Anaj Mandi, Hisar', 'pincode' => '125033'],
                ],
                'Kurukshetra' => [
                    ['name' => 'Thanesar APMC Mandi', 'code' => 'THN-HR-APMC', 'type' => MarketType::APMC, 'address' => 'New Grain Market, Kurukshetra', 'pincode' => '136118'],
                    ['name' => 'Pehowa APMC Mandi', 'code' => 'PHW-APMC', 'type' => MarketType::APMC, 'address' => 'Pehowa Anaj Mandi, Kurukshetra', 'pincode' => '136128'],
                ],
            ],

            // Karnataka (KA)
            'KA' => [
                'Bengaluru Urban' => [
                    ['name' => 'Bengaluru APMC (Yeshwanthpur)', 'code' => 'YST-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Yeshwanthpur Market Yard, Bengaluru', 'pincode' => '560022'],
                    ['name' => 'Singena Agrahara Fruit Terminal', 'code' => 'SGA-APMC', 'type' => MarketType::APMC, 'address' => 'Electronic City Post, Bengaluru', 'pincode' => '560100'],
                ],
                'Belagavi' => [
                    ['name' => 'Belagavi APMC Mandi', 'code' => 'BLG-APMC', 'type' => MarketType::APMC, 'address' => 'APMC Market Yard, Belagavi', 'pincode' => '590001'],
                    ['name' => 'Bailhongal APMC Mandi', 'code' => 'BHG-APMC', 'type' => MarketType::APMC, 'address' => 'Bailhongal Market Yard, Belagavi', 'pincode' => '591102'],
                ],
                'Kalaburagi' => [
                    ['name' => 'Kalaburagi APMC (Tur Dal Capital)', 'code' => 'KLB-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Nehru Gunj Market Yard, Kalaburagi', 'pincode' => '585104'],
                ],
                'Vijayapura' => [
                    ['name' => 'Vijayapura APMC (Chana & Lemon Hub)', 'code' => 'BJP-APMC', 'type' => MarketType::APMC, 'address' => 'APMC Yard, Vijayapura', 'pincode' => '586101'],
                ],
                'Raichur' => [
                    ['name' => 'Raichur APMC (Cotton & Sona Masoori Rice)', 'code' => 'RCR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'APMC Cotton Market, Raichur', 'pincode' => '584101'],
                ],
            ],

            // Tamil Nadu (TN)
            'TN' => [
                'Chennai' => [
                    ['name' => 'Koyambedu Wholesale Market Complex', 'code' => 'KYM-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Koyambedu Market Complex, Chennai', 'pincode' => '600107'],
                ],
                'Erode' => [
                    ['name' => 'Erode Regulated Market (Turmeric Capital)', 'code' => 'ERD-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Perundurai Road, Erode', 'pincode' => '638001'],
                ],
                'Madurai' => [
                    ['name' => 'Madurai Mattuthavani Wholesale Market', 'code' => 'MDU-APMC', 'type' => MarketType::APMC, 'address' => 'Mattuthavani Market, Madurai', 'pincode' => '625007'],
                ],
                'Tiruppur' => [
                    ['name' => 'Tiruppur Regulated Market (Cotton Yard)', 'code' => 'TPR-APMC', 'type' => MarketType::APMC, 'address' => 'Kangeyam Road, Tiruppur', 'pincode' => '641604'],
                ],
            ],

            // Andhra Pradesh (AP)
            'AP' => [
                'Guntur' => [
                    ['name' => 'Guntur Chilli Yard (Asia Largest Red Chilli Market)', 'code' => 'GNT-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Mirchi Yard, Guntur', 'pincode' => '522004'],
                    ['name' => 'Tenali Agricultural Market', 'code' => 'TNL-APMC', 'type' => MarketType::APMC, 'address' => 'Tenali Market Yard, Guntur', 'pincode' => '522201'],
                ],
                'NTR (Vijayawada)' => [
                    ['name' => 'Gollapudi Wholesale Market', 'code' => 'GLP-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Gollapudi Commercial Yard, Vijayawada', 'pincode' => '521225'],
                ],
                'Kurnool' => [
                    ['name' => 'Kurnool Agricultural Market (Onion & Cotton)', 'code' => 'KNL-APMC', 'type' => MarketType::APMC, 'address' => 'C-Camp, Market Yard, Kurnool', 'pincode' => '518002'],
                ],
            ],

            // Telangana (TS)
            'TS' => [
                'Hyderabad' => [
                    ['name' => 'Bowenpally Agricultural Market', 'code' => 'BWN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Bowenpally Market, Secunderabad', 'pincode' => '500011'],
                    ['name' => 'Gaddi Annaram Fruit Market', 'code' => 'GAN-APMC', 'type' => MarketType::APMC, 'address' => 'Batasingaram/Kothapet Yard, Hyderabad', 'pincode' => '501512'],
                    ['name' => 'Malakpet Onion & Chilli Market', 'code' => 'MLK-TS-APMC', 'type' => MarketType::APMC, 'address' => 'Malakpet Market Yard, Hyderabad', 'pincode' => '500036'],
                ],
                'Warangal' => [
                    ['name' => 'Enumamula Agricultural Market (Warangal)', 'code' => 'ENM-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Enumamula Market Yard, Warangal', 'pincode' => '506006'],
                ],
                'Nizamabad' => [
                    ['name' => 'Nizamabad APMC (Turmeric Market)', 'code' => 'NZB-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Dichpally / Nizamabad Yard', 'pincode' => '503001'],
                ],
                'Khammam' => [
                    ['name' => 'Khammam APMC Chilli Market', 'code' => 'KMM-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Khammam Agricultural Market, Khammam', 'pincode' => '507001'],
                ],
            ],

            // Bihar (BR)
            'BR' => [
                'Patna' => [
                    ['name' => 'Patna Bazar Samiti (Bazar Samiti Mandi)', 'code' => 'PAT-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Musallahpur Bazar Samiti, Patna', 'pincode' => '800006'],
                ],
                'Muzaffarpur' => [
                    ['name' => 'Muzaffarpur Litchi & Grain Mandi', 'code' => 'MUZ-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Brahmpura Bazar Samiti, Muzaffarpur', 'pincode' => '842003'],
                ],
                'Purnia' => [
                    ['name' => 'Gulabbagh Mandi (Asia Largest Maize Hub)', 'code' => 'GLB-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Gulabbagh Mandi, Purnia', 'pincode' => '854326'],
                ],
            ],

            // Delhi (DL)
            'DL' => [
                'North Delhi' => [
                    ['name' => 'Azadpur APMC (Asia Largest Fruits & Vegetables Market)', 'code' => 'AZD-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Azadpur Mandi Complex, Delhi', 'pincode' => '110033'],
                    ['name' => 'Narela Grain Market APMC', 'code' => 'NRL-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Narela Anaj Mandi, Delhi', 'pincode' => '110040'],
                ],
                'Central Delhi' => [
                    ['name' => 'Khari Baoli (Asia Largest Spice Market)', 'code' => 'KHB-APMC', 'type' => MarketType::PRIVATE_MARKET, 'address' => 'Khari Baoli, Chandni Chowk, Delhi', 'pincode' => '110006'],
                ],
                'South Delhi' => [
                    ['name' => 'Okhla APMC Mandi', 'code' => 'OKH-APMC', 'type' => MarketType::APMC, 'address' => 'Okhla Mandi Phase-II, New Delhi', 'pincode' => '110020'],
                ],
                'East Delhi' => [
                    ['name' => 'Ghazipur Flower & Grain Market', 'code' => 'GZP-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Ghazipur Terminal Market, Delhi', 'pincode' => '110096'],
                ],
            ],

            // West Bengal (WB)
            'WB' => [
                'Kolkata' => [
                    ['name' => 'Posta Wholesale Bazar', 'code' => 'PST-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Posta Bazar, Burrabazar, Kolkata', 'pincode' => '700007'],
                    ['name' => 'Koley Market', 'code' => 'KLY-APMC', 'type' => MarketType::APMC, 'address' => 'Sealdah, Bowbazar, Kolkata', 'pincode' => '700014'],
                ],
            ],

            // Chhattisgarh (CG)
            'CG' => [
                'Raipur' => [
                    ['name' => 'Raipur APMC (Pandri / Dumartarai Mandi)', 'code' => 'RPR-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Dumartarai Wholesale Market, Raipur', 'pincode' => '492015'],
                ],
                'Dhamtari' => [
                    ['name' => 'Dhamtari APMC (Paddy Hub)', 'code' => 'DMT-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Krishi Upaj Mandi, Dhamtari', 'pincode' => '493773'],
                ],
            ],

            // Himachal Pradesh (HP)
            'HP' => [
                'Shimla' => [
                    ['name' => 'Bhattakufer Fruit & Apple Mandi', 'code' => 'BTK-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Bhattakufer Mandi, Shimla', 'pincode' => '171006'],
                    ['name' => 'Dhalli APMC Mandi', 'code' => 'DHL-HP-APMC', 'type' => MarketType::APMC, 'address' => 'Dhalli Sub-Yard, Shimla', 'pincode' => '171012'],
                ],
                'Solan' => [
                    ['name' => 'Solan APMC (Tomato Capital)', 'code' => 'SLN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Kandaghat / Solan Market Yard, Solan', 'pincode' => '173212'],
                ],
            ],

            // Jammu and Kashmir (JK)
            'JK' => [
                'Srinagar' => [
                    ['name' => 'Parimpora Fruit & Vegetable Mandi', 'code' => 'PRM-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Parimpora Mandi, Srinagar', 'pincode' => '190017'],
                ],
                'Jammu' => [
                    ['name' => 'Narwal Fruit & Grain Mandi', 'code' => 'NRW-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Narwal Mandi Complex, Jammu', 'pincode' => '180006'],
                ],
                'Shopian' => [
                    ['name' => 'Shopian Fruit Mandi (Apple Capital)', 'code' => 'SPN-APMC', 'type' => MarketType::PRINCIPAL_YARD, 'address' => 'Fruit Mandi Aglar, Shopian', 'pincode' => '192303'],
                ],
            ],
        ];

        foreach ($mandisData as $stateCode => $districts) {
            $state = $statesMap->get($stateCode);
            if (! $state) {
                continue;
            }

            foreach ($districts as $districtName => $mandis) {
                $district = District::where('state_id', $state->id)
                    ->where(function ($q) use ($districtName) {
                        $q->where('name', $districtName)
                            ->orWhere('slug', Str::slug($districtName));
                    })
                    ->first();

                if (! $district) {
                    continue;
                }

                foreach ($mandis as $index => $m) {
                    $slug = Str::slug($m['name']);
                    Mandi::updateOrCreate(
                        ['code' => $m['code']],
                        [
                            'district_id' => $district->id,
                            'name' => $m['name'],
                            'slug' => $slug,
                            'market_type' => $m['type']->value,
                            'address' => $m['address'] ?? null,
                            'pincode' => $m['pincode'] ?? null,
                            'sort_order' => $index + 1,
                            'status' => true,
                        ]
                    );
                }
            }
        }
    }
}
