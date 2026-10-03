<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/api/admin/change-password', 'POST', [
    'current_password' => 'password',
    'password' => 'password123',
    'password_confirmation' => 'password123'
]);

$request->headers->set('Accept', 'application/json');

$admin = \App\Models\User::find(1);
$request->setUserResolver(function () use ($admin) {
    return $admin;
});

$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
