<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Trader',
            'Commission Agent',
            'Broker',
            'Farmer',
            'Wholesaler',
            'Retailer',
            'Processor / Miller',
            'Exporter / Importer',
        ];

        foreach ($categories as $category) {
            \App\Models\BusinessCategory::firstOrCreate([
                'name' => $category
            ], [
                'status' => 1
            ]);
        }
    }
}
