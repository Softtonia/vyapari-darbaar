<?php

namespace Database\Seeders;

use App\Enums\ExchangeType;
use App\Models\Exchange;
use Illuminate\Database\Seeder;

class ExchangeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exchanges = [
            [
                'name' => 'NCDEX Limited',
                'slug' => 'ncdex',
                'code' => 'NCDEX',
                'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
                'timezone' => 'Asia/Kolkata',
                'website' => null,
                'default_data_delay_minutes' => null,
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'name' => 'Multi Commodity Exchange of India Limited',
                'slug' => 'mcx',
                'code' => 'MCX',
                'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
                'timezone' => 'Asia/Kolkata',
                'website' => null,
                'default_data_delay_minutes' => null,
                'sort_order' => 2,
                'status' => true,
            ],
        ];

        foreach ($exchanges as $data) {
            Exchange::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
