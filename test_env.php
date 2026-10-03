<?php
try {
    Illuminate\Support\Facades\Redis::ping();
    echo "Redis OK\n";
} catch (\Throwable $e) {
    echo "Redis Error: " . $e->getMessage() . "\n";
}

try {
    $admin = \App\Models\User::find(1);
    app(\App\Services\CampaignEmailService::class)->triggerEvent(
        \App\Enums\CampaignEvent::CHANGE_PASSWORD,
        $admin,
        [
            'UserName' => $admin->name,
            'Username' => $admin->username,
        ]
    );
    echo "Email Service OK\n";
} catch (\Throwable $e) {
    echo "Email Error: " . $e->getMessage() . "\n";
}
