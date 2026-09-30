<?php

namespace Database\Seeders;

use App\Models\DataSource;
use Illuminate\Database\Seeder;

class DataSourceSeeder extends Seeder
{
    public function run(): void
    {
        $dataSources = [
            // Daily Example (07:00 AM)
            [
                'website_name'              => 'Agmarknet',
                'link'                      => 'https://agmarknet.gov.in',
                'description'               => 'Daily mandi rates - 6000+ mandi, min/max/modal',
                'job_type'                  => 'daily',
                'schedule_time'             => '07:00:00',
                'schedule_day_of_week'      => null,
                'schedule_day_of_month'     => null,
                'schedule_month_of_quarter' => null,
                'status'                    => true,
            ],

            // Weekly Example (Every Friday at 18:00 PM)
            [
                'website_name'              => 'DA&FW Sowing Data',
                'link'                      => 'https://agricoop.nic.in',
                'description'               => 'Weekly buwai rakba (Kharif Jun-Sep, Rabi Oct-Jan)',
                'job_type'                  => 'weekly',
                'schedule_time'             => '18:00:00',
                'schedule_day_of_week'      => 5, // 5 = Friday
                'schedule_day_of_month'     => null,
                'schedule_month_of_quarter' => null,
                'status'                    => true,
            ],

            // Monthly Example (1st of every month at 10:00 AM)
            [
                'website_name'              => 'FCI monthly stock position',
                'link'                      => 'https://fci.gov.in',
                'description'               => 'Wheat/rice central pool stock',
                'job_type'                  => 'monthly',
                'schedule_time'             => '10:00:00',
                'schedule_day_of_week'      => null,
                'schedule_day_of_month'     => 1, // Day 1
                'schedule_month_of_quarter' => null,
                'status'                    => true,
            ],

            // Quarterly Example (1st month of quarter on 15th at 14:00 PM)
            [
                'website_name'              => 'ISO World Sugar Balance',
                'link'                      => 'https://isosugar.org',
                'description'               => 'Global surplus/deficit sugar balance',
                'job_type'                  => 'quarterly',
                'schedule_time'             => '14:00:00',
                'schedule_day_of_week'      => null,
                'schedule_day_of_month'     => 15, // Day 15
                'schedule_month_of_quarter' => 1,  // 1st month
                'status'                    => true,
            ],
        ];

        foreach ($dataSources as $source) {
            DataSource::updateOrCreate(
                ['website_name' => $source['website_name']],
                $source
            );
        }
    }
}