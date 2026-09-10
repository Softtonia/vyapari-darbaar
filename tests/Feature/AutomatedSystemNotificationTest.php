<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Jobs\SendUserNotificationJob;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutomatedSystemNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_user_registration_dispatches_automated_welcome_notification(): void
    {
        Queue::fake([SendUserNotificationJob::class]);

        $email = 'newuser@example.com';
        $otpData = app(\App\Services\OtpService::class)->getOrCreateOtp($email, 'registration');
        $otp = $otpData['otp'];

        $response = $this->postJson('/api/user/register', [
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'email' => $email,
            'role' => 'user',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'otp' => $otp,
        ]);

        $response->assertCreated();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);

        Queue::assertPushed(SendUserNotificationJob::class, function ($job) use ($user) {
            return $job->userId === $user->id
                && str_contains($job->title, 'Welcome')
                && $job->type === NotificationType::PUSH_AND_IN_APP;
        });
    }

    public function test_user_login_dispatches_security_alert_notification(): void
    {
        Queue::fake([SendUserNotificationJob::class]);

        $user = User::factory()->create([
            'username' => 'rahul123',
            'password' => Hash::make('Password@123'),
            'status' => 'active',
        ]);
        $user->assignRole('user');

        $response = $this->postJson('/api/user/login', [
            'username' => 'rahul123',
            'password' => 'Password@123',
            'device_name' => 'Chrome Windows',
        ]);

        $response->assertOk();

        Queue::assertPushed(SendUserNotificationJob::class, function ($job) use ($user) {
            return $job->userId === $user->id
                && str_contains($job->title, 'Security Alert: New Login')
                && $job->type === NotificationType::PUSH_AND_IN_APP;
        });
    }

    public function test_user_password_change_dispatches_security_alert_notification(): void
    {
        Queue::fake([SendUserNotificationJob::class]);

        $user = User::factory()->create([
            'password' => Hash::make('OldPassword@123'),
            'status' => 'active',
        ]);
        $user->assignRole('user');

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/user/change-password', [
            'current_password' => 'OldPassword@123',
            'password' => 'NewPassword@123',
            'password_confirmation' => 'NewPassword@123',
        ]);

        $response->assertOk();

        Queue::assertPushed(SendUserNotificationJob::class, function ($job) use ($user) {
            return $job->userId === $user->id
                && str_contains($job->title, 'Password Changed')
                && $job->type === NotificationType::PUSH_AND_IN_APP;
        });
    }

    public function test_user_profile_update_dispatches_in_app_notification(): void
    {
        Queue::fake([SendUserNotificationJob::class]);

        $user = User::factory()->create(['status' => 'active', 'first_name' => 'OldName']);
        $user->assignRole('user');

        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson('/api/user/profile', [
            'first_name' => 'NewName',
        ]);

        $response->assertOk();

        Queue::assertPushed(SendUserNotificationJob::class, function ($job) use ($user) {
            return $job->userId === $user->id
                && str_contains($job->title, 'Profile Updated')
                && $job->type === NotificationType::IN_APP;
        });
    }

    public function test_admin_user_status_update_dispatches_notification(): void
    {
        Queue::fake([SendUserNotificationJob::class]);

        $admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin_test@vyaparidarbar.com',
            'password' => Hash::make('Password@123'),
            'status' => 'active',
        ]);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'admin')->first();
        if ($adminRole) {
            $admin->assignRole($adminRole);
        }

        $user = User::factory()->create(['status' => 'active']);

        $token = $admin->createToken('admin-token', ['*'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/users/{$user->id}/status", [
                'status' => 'suspended',
            ]);

        $response->assertOk();

        Queue::assertPushed(SendUserNotificationJob::class, function ($job) use ($user) {
            return $job->userId === $user->id
                && str_contains($job->title, 'Account Status Updated')
                && $job->type === NotificationType::PUSH_AND_IN_APP;
        });
    }

    public function test_admin_company_status_update_dispatches_notification_to_company_users(): void
    {
        Queue::fake([SendUserNotificationJob::class]);

        $admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin_comp@vyaparidarbar.com',
            'password' => Hash::make('Password@123'),
            'status' => 'active',
        ]);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'admin')->first();
        if ($adminRole) {
            $admin->assignRole($adminRole);
        }

        $user = User::factory()->create(['status' => 'active']);
        $company = Company::create([
            'name' => 'Agro Traders Pvt Ltd',
            'verification_status' => 'pending',
        ]);
        $company->users()->attach($user->id, ['role' => 'trader', 'is_primary' => true]);

        $token = $admin->createToken('admin-token', ['*'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/companies/{$company->id}/status", [
                'verification_status' => 'verified',
            ]);

        $response->assertOk();

        Queue::assertPushed(SendUserNotificationJob::class, function ($job) use ($user) {
            return $job->userId === $user->id
                && str_contains($job->title, 'Company Status Updated')
                && $job->type === NotificationType::PUSH_AND_IN_APP;
        });
    }
}
