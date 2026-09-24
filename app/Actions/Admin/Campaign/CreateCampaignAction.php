<?php

namespace App\Actions\Admin\Campaign;

use App\Models\Campaign;

class CreateCampaignAction
{
    public function execute(array $data): Campaign
    {
        return Campaign::create($data);
    }
}
