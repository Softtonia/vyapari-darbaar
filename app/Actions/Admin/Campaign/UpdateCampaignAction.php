<?php

namespace App\Actions\Admin\Campaign;

use App\Models\Campaign;

class UpdateCampaignAction
{
    public function execute(Campaign $campaign, array $data): Campaign
    {
        $campaign->update($data);
        return $campaign;
    }
}
