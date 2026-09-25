<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $citiesByState = [
            'MH' => ['Mumbai', 'Pune', 'Nagpur', 'Nashik', 'Aurangabad'],
            'DL' => ['New Delhi', 'Central Delhi', 'South Delhi'],
            'KA' => ['Bengaluru', 'Mysuru', 'Mangaluru', 'Hubballi'],
            'TN' => ['Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli'],
            'UP' => ['Lucknow', 'Kanpur', 'Varanasi', 'Agra', 'Noida'],
            'GJ' => ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot'],
            'WB' => ['Kolkata', 'Howrah', 'Darjeeling'],
            'RJ' => ['Jaipur', 'Jodhpur', 'Udaipur', 'Kota'],
            'BR' => ['Patna', 'Gaya', 'Bhagalpur', 'Muzaffarpur'],
            'AP' => ['Visakhapatnam', 'Vijayawada', 'Guntur', 'Tirupati'],
            'TS' => ['Hyderabad', 'Warangal', 'Nizamabad'],
            'KL' => ['Thiruvananthapuram', 'Kochi', 'Kozhikode'],
            'MP' => ['Bhopal', 'Indore', 'Gwalior', 'Jabalpur'],
            'PB' => ['Chandigarh', 'Ludhiana', 'Amritsar', 'Jalandhar'],
            'HR' => ['Gurugram', 'Faridabad', 'Panipat', 'Ambala']
        ];

        foreach ($citiesByState as $stateCode => $cityNames) {
            $state = State::where('code', $stateCode)->first();
            if ($state) {
                foreach ($cityNames as $cityName) {
                    City::updateOrCreate(
                        ['name' => $cityName, 'state_id' => $state->id],
                        ['status' => true]
                    );
                }
            }
        }
    }
}
