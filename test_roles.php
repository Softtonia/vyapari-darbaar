<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\Models\User::where('email', 'vijay.kumar@softtonia.com')->first();
if ($user) {
    echo json_encode($user->roles->pluck('name'));
} else {
    echo 'No user';
}
