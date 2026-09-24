<?php

namespace App\Console\Commands;

use App\Enums\CampaignSendType;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use Illuminate\Console\Command;

class DispatchScheduledCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:dispatch-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch scheduled email campaigns that are due';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $campaigns = Campaign::where('is_active', true)
            ->where('send_type', CampaignSendType::SCHEDULE->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($campaigns as $campaign) {
            $this->info("Dispatching campaign: {$campaign->name}");
            $campaign->update(['is_active' => false]);
            DispatchCampaignJob::dispatch($campaign);
        }

        $this->info('Scheduled campaigns dispatched successfully.');
    }
}
