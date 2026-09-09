<?php

namespace Tests\Feature;

use App\Mail\DiagnosticTestMail;
use App\Models\Admin;
use App\Models\Role;
use App\Models\SmtpSetting;
use App\Models\User;
use App\Services\DynamicMailConfigService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DynamicSmtpSettingTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'admin')->first();
        $this->admin->assignRole($adminRole);

        $this->adminToken = $this->admin->createToken('admin-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ];
    }

    // ==========================================
    // 1. GET SMTP SETTINGS TESTS
    // ==========================================

    public function test_get_smtp_settings_requires_authentication(): void
    {
        $this->getJson('/api/admin/settings/smtp')
            ->assertStatus(401);
    }

    public function test_normal_user_cannot_access_smtp_settings(): void
    {
        $user = User::create([
            'username' => 'normal_user',
            'name' => 'Normal User',
            'phone' => '9876543210',
            'email' => 'user@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $token = $user->createToken('user-token')->plainTextToken;

        $this->getJson('/api/admin/settings/smtp', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->assertStatus(403);
    }

    public function test_admin_without_view_permission_is_rejected(): void
    {
        $limitedAdmin = Admin::create([
            'first_name' => 'Limited',
            'last_name' => 'Admin',
            'email' => 'limited@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $token = $limitedAdmin->createToken('token')->plainTextToken;

        $this->getJson('/api/admin/settings/smtp', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->assertStatus(403);
    }

    public function test_get_smtp_settings_returns_null_when_not_configured(): void
    {
        $response = $this->getJson('/api/admin/settings/smtp', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'SMTP settings not configured.',
                'data' => null,
            ]);
    }

    public function test_get_smtp_settings_returns_safe_data_without_password(): void
    {
        SmtpSetting::create([
            'host' => 'smtp.zoho.in',
            'port' => 465,
            'scheme' => 'smtps',
            'username' => 'noreply@vyaparidarbar.com',
            'password' => 'secret-smtp-pass',
            'from_address' => 'noreply@vyaparidarbar.com',
            'from_name' => 'Vyapari Darbar',
            'status' => true,
        ]);

        $response = $this->getJson('/api/admin/settings/smtp', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'SMTP settings fetched successfully.',
                'data' => [
                    'host' => 'smtp.zoho.in',
                    'port' => 465,
                    'scheme' => 'smtps',
                    'username' => 'noreply@vyaparidarbar.com',
                    'from_address' => 'noreply@vyaparidarbar.com',
                    'from_name' => 'Vyapari Darbar',
                    'status' => true,
                    'password_configured' => true,
                ],
            ]);

        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.password_ciphertext');
    }

    // ==========================================
    // 2. PUT UPDATE SMTP SETTINGS TESTS
    // ==========================================

    public function test_initial_put_requires_password(): void
    {
        $this->putJson('/api/admin/settings/smtp', [
            'host' => 'smtp.zoho.in',
            'port' => 465,
            'scheme' => 'smtps',
            'username' => 'noreply@vyaparidarbar.com',
            'from_address' => 'noreply@vyaparidarbar.com',
            'from_name' => 'Vyapari Darbar',
            'status' => true,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_admin_can_create_and_update_singleton_smtp_settings(): void
    {
        // 1. Initial creation
        $res1 = $this->putJson('/api/admin/settings/smtp', [
            'host' => 'smtp.zoho.in',
            'port' => 465,
            'scheme' => 'smtps',
            'username' => 'noreply@vyaparidarbar.com',
            'password' => 'first-pass',
            'from_address' => 'noreply@vyaparidarbar.com',
            'from_name' => 'Vyapari Darbar',
            'status' => true,
        ], $this->authHeaders());

        $res1->assertStatus(200)
            ->assertJsonPath('data.host', 'smtp.zoho.in')
            ->assertJsonPath('data.password_configured', true);

        $this->assertEquals(1, SmtpSetting::count());
        $setting = SmtpSetting::find(1);
        $this->assertEquals('first-pass', $setting->password); // decrypted via cast

        // Direct DB check to ensure encrypted at rest
        $rawDbPassword = DB::table('smtp_settings')->where('id', 1)->value('password');
        $this->assertNotEquals('first-pass', $rawDbPassword);

        // 2. Update omitting password retains old password
        $res2 = $this->putJson('/api/admin/settings/smtp', [
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'scheme' => 'smtp',
            'username' => 'postmaster@vyaparidarbar.com',
            'from_address' => 'noreply@vyaparidarbar.com',
            'from_name' => 'Vyapari Darbar Mailgun',
            'status' => true,
        ], $this->authHeaders());

        $res2->assertStatus(200)
            ->assertJsonPath('data.host', 'smtp.mailgun.org')
            ->assertJsonPath('data.password_configured', true);

        $settingFresh = SmtpSetting::find(1);
        $this->assertEquals('first-pass', $settingFresh->password);
        $this->assertEquals('smtp.mailgun.org', $settingFresh->host);

        // 3. Update with placeholder "********" also retains old password
        $this->putJson('/api/admin/settings/smtp', [
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'scheme' => 'smtp',
            'username' => 'postmaster@vyaparidarbar.com',
            'password' => '********',
            'from_address' => 'noreply@vyaparidarbar.com',
            'from_name' => 'Vyapari Darbar Mailgun',
            'status' => true,
        ], $this->authHeaders())->assertStatus(200);

        $this->assertEquals('first-pass', SmtpSetting::find(1)->password);

        // 4. Update with new password replaces it securely
        $this->putJson('/api/admin/settings/smtp', [
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'scheme' => 'smtp',
            'username' => 'postmaster@vyaparidarbar.com',
            'password' => 'new-secret-2026',
            'from_address' => 'noreply@vyaparidarbar.com',
            'from_name' => 'Vyapari Darbar Mailgun',
            'status' => true,
        ], $this->authHeaders())->assertStatus(200);

        $this->assertEquals('new-secret-2026', SmtpSetting::find(1)->password);
        $this->assertEquals(1, SmtpSetting::count());
    }

    public function test_put_validation_rules_enforced(): void
    {
        // Invalid port
        $this->putJson('/api/admin/settings/smtp', [
            'host' => 'smtp.example.com',
            'port' => 70000,
            'scheme' => 'smtp',
            'username' => 'user',
            'password' => 'pass',
            'from_address' => 'user@example.com',
            'from_name' => 'Name',
            'status' => true,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['port']);

        // Invalid scheme
        $this->putJson('/api/admin/settings/smtp', [
            'host' => 'smtp.example.com',
            'port' => 587,
            'scheme' => 'http',
            'username' => 'user',
            'password' => 'pass',
            'from_address' => 'user@example.com',
            'from_name' => 'Name',
            'status' => true,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheme']);

        // Invalid email
        $this->putJson('/api/admin/settings/smtp', [
            'host' => 'smtp.example.com',
            'port' => 587,
            'scheme' => 'smtp',
            'username' => 'user',
            'password' => 'pass',
            'from_address' => 'not-an-email',
            'from_name' => 'Name',
            'status' => true,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_address']);
    }

    // ==========================================
    // 3. RUNTIME CONFIGURATION & FALLBACK TESTS
    // ==========================================

    public function test_dynamic_configuration_applied_and_fallback_restored(): void
    {
        $service = app(DynamicMailConfigService::class);

        // 1. Create active DB SMTP
        SmtpSetting::create([
            'host' => 'smtp.dynamic-db.com',
            'port' => 2525,
            'scheme' => 'smtp',
            'username' => 'dyn_user',
            'password' => 'dyn_pass',
            'from_address' => 'dyn@vyaparidarbar.com',
            'from_name' => 'Dynamic DB Sender',
            'status' => true,
        ]);

        $service->apply();

        $this->assertEquals('smtp.dynamic-db.com', config('mail.mailers.smtp.host'));
        $this->assertEquals(2525, config('mail.mailers.smtp.port'));
        $this->assertEquals('dyn_user', config('mail.mailers.smtp.username'));
        $this->assertEquals('dyn_pass', config('mail.mailers.smtp.password'));
        $this->assertEquals('dyn@vyaparidarbar.com', config('mail.from.address'));
        $this->assertEquals('Dynamic DB Sender', config('mail.from.name'));

        // 2. Disable DB SMTP (status = false)
        SmtpSetting::find(1)->update(['status' => false]);
        Cache::forget(DynamicMailConfigService::CACHE_KEY);

        $service->apply();

        // Must restore original fallback config
        $this->assertNotEquals('smtp.dynamic-db.com', config('mail.mailers.smtp.host'));
    }

    // ==========================================
    // 4. TEST SMTP ENDPOINT TESTS
    // ==========================================

    public function test_test_endpoint_requires_permission(): void
    {
        $limitedAdmin = Admin::create([
            'first_name' => 'Limited',
            'last_name' => 'Admin',
            'email' => 'limited_test@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $token = $limitedAdmin->createToken('token')->plainTextToken;

        $this->postJson('/api/admin/settings/smtp/test', [
            'recipient' => 'test@example.com',
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->assertStatus(403);
    }

    public function test_test_endpoint_returns_422_when_no_smtp_configured(): void
    {
        $this->postJson('/api/admin/settings/smtp/test', [
            'recipient' => 'test@example.com',
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'SMTP configuration is not configured.',
                'error' => 'SMTP_CONFIGURATION_MISSING',
            ]);
    }

    public function test_test_endpoint_tests_saved_db_smtp_even_if_status_disabled(): void
    {
        Mail::fake();

        // Create disabled SMTP record
        SmtpSetting::create([
            'host' => 'smtp.disabled-test.com',
            'port' => 465,
            'scheme' => 'smtps',
            'username' => 'disabled_user',
            'password' => 'secret',
            'from_address' => 'disabled@vyaparidarbar.com',
            'from_name' => 'Disabled Sender',
            'status' => false,
        ]);

        $response = $this->postJson('/api/admin/settings/smtp/test', [
            'recipient' => 'diagnostics@example.com',
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'SMTP test email sent successfully.',
            ]);

        Mail::assertSent(DiagnosticTestMail::class, function (DiagnosticTestMail $mail) {
            return $mail->hasTo('diagnostics@example.com');
        });
    }

    public function test_test_endpoint_is_rate_limited(): void
    {
        Mail::fake();

        SmtpSetting::create([
            'host' => 'smtp.zoho.in',
            'port' => 465,
            'scheme' => 'smtps',
            'username' => 'noreply@vyaparidarbar.com',
            'password' => 'secret',
            'from_address' => 'noreply@vyaparidarbar.com',
            'from_name' => 'Vyapari Darbar',
            'status' => true,
        ]);

        // 5 allowed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admin/settings/smtp/test', [
                'recipient' => 'test@example.com',
            ], $this->authHeaders())->assertStatus(200);
        }

        // 6th attempt should be throttled (429)
        $this->postJson('/api/admin/settings/smtp/test', [
            'recipient' => 'test@example.com',
        ], $this->authHeaders())->assertStatus(429);
    }
}
