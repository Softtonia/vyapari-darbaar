<?php

namespace App\Actions\Admin\Campaign;

use App\Models\Campaign;

class CreateCampaignAction
{
    public function execute(array $data): Campaign
    {
        $campaign = Campaign::create($data);

        if ($campaign->send_type->value === 'now' && $campaign->is_active) {
            \App\Jobs\DispatchCampaignJob::dispatch($campaign);
            // Mark inactive so it doesn't send again
            $campaign->update(['is_active' => false]);
        }

        return $campaign;
    }
}
