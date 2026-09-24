<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = \App\Models\EmailTemplate::all()->keyBy('key');

        $campaigns = [
            [
                'name' => 'Welcome New User Trigger',
                'email_template_id' => $templates->get('CAMPAIGN_WELCOME_USER')?->id,
                'send_type' => 'trigger',
                'event' => \App\Enums\CampaignEvent::WELCOME_USER->value,
                'is_active' => true,
            ],
            [
                'name' => 'User Registration Trigger',
                'email_template_id' => $templates->get('CAMPAIGN_WELCOME_USER')?->id,
                'send_type' => 'trigger',
                'event' => \App\Enums\CampaignEvent::REGISTER->value,
                'is_active' => true,
            ],
            [
                'name' => 'Forgot Password Trigger',
                'email_template_id' => $templates->get('CAMPAIGN_FORGET_PASSWORD')?->id,
                'send_type' => 'trigger',
                'event' => \App\Enums\CampaignEvent::FORGET_PASSWORD->value,
                'is_active' => true,
            ],
            [
                'name' => 'Password Changed Trigger',
                'email_template_id' => $templates->get('CAMPAIGN_CHANGE_PASSWORD')?->id,
                'send_type' => 'trigger',
                'event' => \App\Enums\CampaignEvent::CHANGE_PASSWORD->value,
                'is_active' => true,
            ],
            [
                'name' => 'Profile Updated Trigger',
                'email_template_id' => $templates->get('CAMPAIGN_UPDATE_PROFILE')?->id,
                'send_type' => 'trigger',
                'event' => \App\Enums\CampaignEvent::UPDATE_PROFILE->value,
                'is_active' => true,
            ],
        ];

        foreach ($campaigns as $campaign) {
            if ($campaign['email_template_id']) {
                \App\Models\Campaign::updateOrCreate(
                    [
                        'event' => $campaign['event'],
                        'send_type' => 'trigger'
                    ],
                    $campaign
                );
            }
        }
    }
}
