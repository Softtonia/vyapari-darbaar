<?php

namespace Database\Seeders;

use App\Enums\CampaignEvent;
use App\Enums\CampaignSendType;
use App\Models\Campaign;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $welcomeTemplate = EmailTemplate::where('key', 'USER_ACCOUNT_CREATED')->first();

        if ($welcomeTemplate) {
            Campaign::updateOrCreate(
                ['name' => 'Welcome New User Trigger'],
                [
                    'email_template_id' => $welcomeTemplate->id,
                    'send_type' => CampaignSendType::TRIGGER->value,
                    'event' => CampaignEvent::REGISTER->value,
                    'is_active' => true,
                ]
            );
            
            Campaign::updateOrCreate(
                ['name' => 'Account Created Trigger'],
                [
                    'email_template_id' => $welcomeTemplate->id,
                    'send_type' => CampaignSendType::TRIGGER->value,
                    'event' => CampaignEvent::ADD_USER->value,
                    'is_active' => true,
                ]
            );
        }
    }
}
