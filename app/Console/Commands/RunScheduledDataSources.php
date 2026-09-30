<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunScheduledDataSources extends Command
{
    protected $signature = 'datasources:run-scheduled';
    protected $description = 'Database schedule ke hisaab se due data sources ko trigger karein';

    public function handle(): void
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:00');
        $currentDayOfWeek = $now->dayOfWeek; // 0=Sunday, 5=Friday
        $currentDayOfMonth = $now->day;
        $currentMonthOfQuarter = (($now->month - 1) % 3) + 1; // 1, 2, ya 3

        $dataSources = DataSource::where('status', true)->get();

        foreach ($dataSources as $source) {
            // Schedule time check (matching current minute)
            if ($source->schedule_time && Carbon::parse($source->schedule_time)->format('H:i:00') !== $currentTime) {
                continue;
            }

            $isDue = false;

            switch ($source->job_type) {
                case 'daily':
                    $isDue = true;
                    break;

                case 'weekly':
                    if ($source->schedule_day_of_week === null || $source->schedule_day_of_week === $currentDayOfWeek) {
                        $isDue = true;
                    }
                    break;

                case 'monthly':
                    if ($source->schedule_day_of_month === null || $source->schedule_day_of_month === $currentDayOfMonth) {
                        $isDue = true;
                    }
                    break;

                case 'quarterly':
                    $quarterMonthMatch = ($source->schedule_month_of_quarter === null || $source->schedule_month_of_quarter === $currentMonthOfQuarter);
                    $dayMatch = ($source->schedule_day_of_month === null || $source->schedule_day_of_month === $currentDayOfMonth);

                    if ($quarterMonthMatch && $dayMatch) {
                        $isDue = true;
                    }
                    break;
            }

            if ($isDue) {
                $this->info("Triggering data source: {$source->website_name}");
                // Trigger the data source job here
                
            }
        }
    }
}