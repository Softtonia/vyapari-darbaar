<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\User;
use App\Notifications\CampaignEmailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $campaign;

    /**
     * Create a new job instance.
     */
    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->campaign->send_type->value === 'trigger' && !$this->campaign->is_active) {
            return;
        }

        $template = $this->campaign->emailTemplate;
        if (!$template || !$template->is_active) {
            return;
        }

        // Get active users based on target_users if specified
        $query = User::where('status', 'active');
        if (!empty($this->campaign->target_users)) {
            $query->whereIn('id', $this->campaign->target_users);
        }
        $users = $query->get();

        foreach ($users as $user) {
            // Replace placeholders
            $placeholders = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ];

            $subject = $this->replacePlaceholders($template->subject, $placeholders);
            $body = $this->replacePlaceholders($template->body, $placeholders);

            $user->notify(new CampaignEmailNotification($subject, $body));
        }

    }

    private function replacePlaceholders(string $content, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
        }
        return $content;
    }
}
