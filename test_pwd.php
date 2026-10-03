<?php
config(['app.debug' => true]);

$request = Illuminate\Http\Request::create(
    '/api/admin/change-password', 'POST',
    ['current_password' => 'password', 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123']
);
$request->headers->set('Accept', 'application/json');
$admin = \App\Models\User::find(1);
$admin->password = \Illuminate\Support\Facades\Hash::make('password');
$admin->save();
$request->setUserResolver(function() use ($admin) { return $admin; });

$controller = app(\App\Http\Controllers\Api\Admin\AdminAuthController::class);
$formRequest = \App\Http\Requests\Admin\ChangeAdminPasswordRequest::createFrom($request);
$formRequest->setUserResolver(function() use ($admin) { return $admin; });
$formRequest->setContainer(app());

try {
    $formRequest->validateResolved();
    $action = app(\App\Actions\Admin\Profile\ChangeAdminPasswordAction::class);
    $response = $controller->changePassword($formRequest, $action);
    echo "RESPONSE:\n" . $response->getContent() . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
