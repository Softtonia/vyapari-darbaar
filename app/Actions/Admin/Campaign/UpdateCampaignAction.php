<?php

namespace App\Actions\Admin\Campaign;

use App\Models\Campaign;

class UpdateCampaignAction
{
    public function execute(Campaign $campaign, array $data): Campaign
    {
        $campaign->update($data);

        if ($campaign->send_type->value === 'now' && $campaign->is_active) {
            \App\Jobs\DispatchCampaignJob::dispatch($campaign);
            $campaign->update(['is_active' => false]);
        }

        return $campaign;
    }
}
