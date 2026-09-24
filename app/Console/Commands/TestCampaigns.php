<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Campaign;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Actions\Admin\Campaign\CreateCampaignAction;
use Illuminate\Support\Facades\Artisan;

class TestCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:campaigns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the campaign email sending system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Creating a dummy user for testing if none exists...");
        $user = User::first();
        if (!$user) {
            $this->error("No users found.");
            return;
        }

        $this->info("Getting a template...");
        $template = EmailTemplate::first();
        if (!$template) {
            $this->error("No email templates found. Please run db:seed first.");
            return;
        }

        $this->info("Creating a SEND NOW campaign...");
        $action = new CreateCampaignAction();
        
        $campaign = $action->execute([
            'name' => 'Test Send Now Campaign',
            'email_template_id' => $template->id,
            'send_type' => 'now',
            'is_active' => true,
        ]);

        $this->info("Campaign created and job dispatched!");
        $this->info("Running queue worker synchronously to process the job...");
        
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--once' => true,
        ]);

        $this->info("Queue processed. Output:");
        $this->line(Artisan::output());

        $this->info("Testing Trigger via CampaignEmailService...");
        // Ensure a trigger campaign exists
        Campaign::updateOrCreate(
            [
                'name' => 'Test Trigger Campaign',
                'event' => 'register'
            ],
            [
                'email_template_id' => $template->id,
                'send_type' => 'trigger',
                'is_active' => true,
            ]
        );

        app(\App\Services\CampaignEmailService::class)->triggerEvent(
            \App\Enums\CampaignEvent::REGISTER,
            $user,
            ['name' => 'Test User', 'email' => 'test@example.com']
        );
        $this->info("Trigger dispatched. Processing queue again...");
        
        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
        ]);

        $this->line(Artisan::output());
        $this->info("All tests passed!");
    }
}
