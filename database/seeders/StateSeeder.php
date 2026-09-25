<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Comprehensive Master list of all 28 States and 8 Union Territories of India.
     */
    public function run(): void
    {
        $states = [
            // 28 States
            ['name' => 'Andhra Pradesh', 'slug' => 'andhra-pradesh', 'code' => 'AP', 'sort_order' => 1, 'status' => true],
            ['name' => 'Arunachal Pradesh', 'slug' => 'arunachal-pradesh', 'code' => 'AR', 'sort_order' => 2, 'status' => true],
            ['name' => 'Assam', 'slug' => 'assam', 'code' => 'AS', 'sort_order' => 3, 'status' => true],
            ['name' => 'Bihar', 'slug' => 'bihar', 'code' => 'BR', 'sort_order' => 4, 'status' => true],
            ['name' => 'Chhattisgarh', 'slug' => 'chhattisgarh', 'code' => 'CG', 'sort_order' => 5, 'status' => true],
            ['name' => 'Goa', 'slug' => 'goa', 'code' => 'GA', 'sort_order' => 6, 'status' => true],
            ['name' => 'Gujarat', 'slug' => 'gujarat', 'code' => 'GJ', 'sort_order' => 7, 'status' => true],
            ['name' => 'Haryana', 'slug' => 'haryana', 'code' => 'HR', 'sort_order' => 8, 'status' => true],
            ['name' => 'Himachal Pradesh', 'slug' => 'himachal-pradesh', 'code' => 'HP', 'sort_order' => 9, 'status' => true],
            ['name' => 'Jharkhand', 'slug' => 'jharkhand', 'code' => 'JH', 'sort_order' => 10, 'status' => true],
            ['name' => 'Karnataka', 'slug' => 'karnataka', 'code' => 'KA', 'sort_order' => 11, 'status' => true],
            ['name' => 'Kerala', 'slug' => 'kerala', 'code' => 'KL', 'sort_order' => 12, 'status' => true],
            ['name' => 'Madhya Pradesh', 'slug' => 'madhya-pradesh', 'code' => 'MP', 'sort_order' => 13, 'status' => true],
            ['name' => 'Maharashtra', 'slug' => 'maharashtra', 'code' => 'MH', 'sort_order' => 14, 'status' => true],
            ['name' => 'Manipur', 'slug' => 'manipur', 'code' => 'MN', 'sort_order' => 15, 'status' => true],
            ['name' => 'Meghalaya', 'slug' => 'meghalaya', 'code' => 'ML', 'sort_order' => 16, 'status' => true],
            ['name' => 'Mizoram', 'slug' => 'mizoram', 'code' => 'MZ', 'sort_order' => 17, 'status' => true],
            ['name' => 'Nagaland', 'slug' => 'nagaland', 'code' => 'NL', 'sort_order' => 18, 'status' => true],
            ['name' => 'Odisha', 'slug' => 'odisha', 'code' => 'OD', 'sort_order' => 19, 'status' => true],
            ['name' => 'Punjab', 'slug' => 'punjab', 'code' => 'PB', 'sort_order' => 20, 'status' => true],
            ['name' => 'Rajasthan', 'slug' => 'rajasthan', 'code' => 'RJ', 'sort_order' => 21, 'status' => true],
            ['name' => 'Sikkim', 'slug' => 'sikkim', 'code' => 'SK', 'sort_order' => 22, 'status' => true],
            ['name' => 'Tamil Nadu', 'slug' => 'tamil-nadu', 'code' => 'TN', 'sort_order' => 23, 'status' => true],
            ['name' => 'Telangana', 'slug' => 'telangana', 'code' => 'TS', 'sort_order' => 24, 'status' => true],
            ['name' => 'Tripura', 'slug' => 'tripura', 'code' => 'TR', 'sort_order' => 25, 'status' => true],
            ['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh', 'code' => 'UP', 'sort_order' => 26, 'status' => true],
            ['name' => 'Uttarakhand', 'slug' => 'uttarakhand', 'code' => 'UK', 'sort_order' => 27, 'status' => true],
            ['name' => 'West Bengal', 'slug' => 'west-bengal', 'code' => 'WB', 'sort_order' => 28, 'status' => true],

            // 8 Union Territories
            ['name' => 'Andaman and Nicobar Islands', 'slug' => 'andaman-and-nicobar-islands', 'code' => 'AN', 'sort_order' => 29, 'status' => true],
            ['name' => 'Chandigarh', 'slug' => 'chandigarh', 'code' => 'CH', 'sort_order' => 30, 'status' => true],
            ['name' => 'Dadra and Nagar Haveli and Daman and Diu', 'slug' => 'dadra-and-nagar-haveli-and-daman-and-diu', 'code' => 'DN', 'sort_order' => 31, 'status' => true],
            ['name' => 'Delhi', 'slug' => 'delhi', 'code' => 'DL', 'sort_order' => 32, 'status' => true],
            ['name' => 'Jammu and Kashmir', 'slug' => 'jammu-and-kashmir', 'code' => 'JK', 'sort_order' => 33, 'status' => true],
            ['name' => 'Ladakh', 'slug' => 'ladakh', 'code' => 'LA', 'sort_order' => 34, 'status' => true],
            ['name' => 'Lakshadweep', 'slug' => 'lakshadweep', 'code' => 'LD', 'sort_order' => 35, 'status' => true],
            ['name' => 'Puducherry', 'slug' => 'puducherry', 'code' => 'PY', 'sort_order' => 36, 'status' => true],
        ];

        $india = \App\Models\Country::where('code', 'IN')->first();
        if (!$india) {
            return;
        }

        foreach ($states as $state) {
            $stateData = array_merge($state, ['country_id' => $india->id]);
            State::updateOrCreate(
                ['code' => $state['code']],
                $stateData
            );
        }
    }
}
