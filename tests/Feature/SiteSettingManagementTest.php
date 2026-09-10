<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SiteSettingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SiteSettingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Cache::flush();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SiteSettingSeeder::class);

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
    // 1. PUBLIC ENDPOINT TESTS
    // ==========================================

    public function test_public_endpoint_accessible_without_auth(): void
    {
        $response = $this->getJson('/api/site-settings');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Site settings fetched successfully.',
                'data' => [
                    'site_name' => 'Vyapari Darbar',
                    'site_title' => null,
                    'site_description' => null,
                    'web_logo' => null,
                    'mobile_logo' => null,
                ],
            ]);

        // Audit fields and database IDs must not be exposed
        $response->assertJsonMissingPath('data.id');
        $response->assertJsonMissingPath('data.created_by');
        $response->assertJsonMissingPath('data.updated_by');
        $response->assertJsonMissingPath('data.created_at');
        $response->assertJsonMissingPath('data.updated_at');
    }

    public function test_public_endpoint_returns_public_logo_urls(): void
    {
        $setting = SiteSetting::find(1);
        $setting->update([
            'web_logo' => 'site-settings/logos/test-web.png',
            'mobile_logo' => 'site-settings/logos/test-mobile.png',
        ]);
        Cache::forget(SiteSettingService::PUBLIC_CACHE_KEY);

        $response = $this->getJson('/api/site-settings');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertStringContainsString('storage/site-settings/logos/test-web.png', $data['web_logo']);
        $this->assertStringContainsString('storage/site-settings/logos/test-mobile.png', $data['mobile_logo']);
    }

    public function test_public_endpoint_uses_cache(): void
    {
        // First fetch populates cache
        $this->getJson('/api/site-settings');
        $this->assertTrue(Cache::has(SiteSettingService::PUBLIC_CACHE_KEY));

        // Directly modify DB without clearing cache
        DB::table('site_settings')->where('id', 1)->update(['site_name' => 'Direct DB Change']);

        // Next GET should still return cached value
        $response = $this->getJson('/api/site-settings');
        $response->assertStatus(200)
            ->assertJsonPath('data.site_name', 'Vyapari Darbar');

        // Clear cache and verify fresh fetch
        Cache::forget(SiteSettingService::PUBLIC_CACHE_KEY);
        $responseFresh = $this->getJson('/api/site-settings');
        $responseFresh->assertStatus(200)
            ->assertJsonPath('data.site_name', 'Direct DB Change');
    }

    // ==========================================
    // 2. ADMIN AUTHORIZATION & ACCESS TESTS
    // ==========================================

    public function test_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/site-settings')
            ->assertStatus(401);

        $this->patchJson('/api/admin/site-settings', ['site_name' => 'New Name'])
            ->assertStatus(401);
    }

    public function test_user_token_is_rejected_on_admin_site_settings(): void
    {
        $user = User::create([
            'username' => 'normaluser',
            'name' => 'Normal User',
            'phone' => '9876543210',
            'email' => 'user@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);
        $userToken = $user->createToken('user-token')->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $userToken,
            'Accept' => 'application/json',
        ];

        $this->getJson('/api/admin/site-settings', $headers)
            ->assertStatus(403);

        $this->patchJson('/api/admin/site-settings', ['site_name' => 'New Name'], $headers)
            ->assertStatus(403);
    }

    public function test_admin_without_view_permission_is_rejected(): void
    {
        $adminLimited = Admin::create([
            'first_name' => 'Limited',
            'last_name' => 'Admin',
            'email' => 'limited@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);
        $token = $adminLimited->createToken('limited-token')->plainTextToken;

        $this->getJson('/api/admin/site-settings', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->assertStatus(403);
    }

    public function test_admin_with_view_permission_can_get_site_settings(): void
    {
        $response = $this->getJson('/api/admin/site-settings', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Site settings fetched successfully.',
                'data' => [
                    'id' => 1,
                    'site_name' => 'Vyapari Darbar',
                ],
            ]);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'site_name',
                'site_title',
                'site_description',
                'web_logo',
                'mobile_logo',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_admin_without_update_permission_is_rejected(): void
    {
        $adminViewOnly = Admin::create([
            'first_name' => 'ViewOnly',
            'last_name' => 'Admin',
            'email' => 'viewonly@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);
        $adminViewOnly->givePermissionTo('site-setting.view');

        $token = $adminViewOnly->createToken('view-token')->plainTextToken;

        $this->patchJson('/api/admin/site-settings', [
            'site_name' => 'Unauthorized Update',
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->assertStatus(403);
    }

    // ==========================================
    // 3. TEXT UPDATE TESTS
    // ==========================================

    public function test_admin_can_update_text_fields_partially(): void
    {
        $response = $this->patchJson('/api/admin/site-settings', [
            'site_name' => 'Vyapari Darbaar Global',
            'site_title' => 'India Premier Mandi Platform',
            'site_description' => 'Connecting mandi traders across India.',
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Site settings updated successfully.',
                'data' => [
                    'site_name' => 'Vyapari Darbaar Global',
                    'site_title' => 'India Premier Mandi Platform',
                    'site_description' => 'Connecting mandi traders across India.',
                ],
            ]);

        $this->assertDatabaseHas('site_settings', [
            'id' => 1,
            'site_name' => 'Vyapari Darbaar Global',
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_blank_nullable_text_normalizes_to_null(): void
    {
        SiteSetting::find(1)->update([
            'site_title' => 'Initial Title',
            'site_description' => 'Initial Description',
        ]);

        $response = $this->patchJson('/api/admin/site-settings', [
            'site_title' => '   ',
            'site_description' => '',
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('data.site_title', null)
            ->assertJsonPath('data.site_description', null);

        $this->assertDatabaseHas('site_settings', [
            'id' => 1,
            'site_title' => null,
            'site_description' => null,
        ]);
    }

    public function test_validation_rules_enforced(): void
    {
        // site_name cannot be empty string
        $this->patchJson('/api/admin/site-settings', [
            'site_name' => '   ',
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['site_name']);

        // site_name max 150
        $this->patchJson('/api/admin/site-settings', [
            'site_name' => str_repeat('a', 151),
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['site_name']);
    }

    // ==========================================
    // 4. LOGO UPLOADS & FILE REPLACEMENT TESTS
    // ==========================================

    public function test_upload_valid_web_logo_and_method_spoofing(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        // Upload using POST multipart with _method=PATCH
        $response = $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'site_name' => 'Vyapari Darbar',
            'web_logo' => $file,
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $setting = SiteSetting::find(1);

        $this->assertNotNull($setting->web_logo);
        $this->assertNull($setting->mobile_logo);
        Storage::disk('public')->assertExists($setting->web_logo);
    }

    public function test_upload_valid_formats_jpg_png_webp(): void
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $file = UploadedFile::fake()->image("logo.{$extension}", 100, 100);

            $response = $this->post('/api/admin/site-settings', [
                '_method' => 'PATCH',
                'web_logo' => $file,
            ], $this->authHeaders());

            $response->assertStatus(200);
        }
    }

    public function test_invalid_file_and_svg_and_size_limit_rejected(): void
    {
        // 1. Text file rejected
        $txtFile = UploadedFile::fake()->create('document.txt', 100, 'text/plain');
        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'web_logo' => $txtFile,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['web_logo']);

        // 2. SVG file rejected
        $svgFile = UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml');
        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'web_logo' => $svgFile,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['web_logo']);

        // 3. File > 2048 KB rejected
        $largeFile = UploadedFile::fake()->image('large.jpg')->size(2049);
        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'web_logo' => $largeFile,
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['web_logo']);
    }

    public function test_replacing_web_logo_deletes_old_file_and_preserves_mobile_logo(): void
    {
        // Setup initial logos
        $file1 = UploadedFile::fake()->image('web1.png', 100, 100);
        $file2 = UploadedFile::fake()->image('mobile1.png', 100, 100);

        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'web_logo' => $file1,
            'mobile_logo' => $file2,
        ], $this->authHeaders())->assertStatus(200);

        $setting1 = SiteSetting::find(1);
        $oldWebPath = $setting1->web_logo;
        $oldMobilePath = $setting1->mobile_logo;

        Storage::disk('public')->assertExists($oldWebPath);
        Storage::disk('public')->assertExists($oldMobilePath);

        // Replace only web logo
        $file3 = UploadedFile::fake()->image('web2.png', 100, 100);
        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'web_logo' => $file3,
        ], $this->authHeaders())->assertStatus(200);

        $setting2 = SiteSetting::find(1);
        $newWebPath = $setting2->web_logo;

        $this->assertNotEquals($oldWebPath, $newWebPath);
        $this->assertEquals($oldMobilePath, $setting2->mobile_logo);

        // Old web logo should be deleted, new web logo exists, mobile logo untouched
        Storage::disk('public')->assertMissing($oldWebPath);
        Storage::disk('public')->assertExists($newWebPath);
        Storage::disk('public')->assertExists($oldMobilePath);
    }

    public function test_replacing_mobile_logo_deletes_old_file_and_preserves_web_logo(): void
    {
        $file1 = UploadedFile::fake()->image('web.png', 100, 100);
        $file2 = UploadedFile::fake()->image('mobile1.png', 100, 100);

        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'web_logo' => $file1,
            'mobile_logo' => $file2,
        ], $this->authHeaders())->assertStatus(200);

        $setting1 = SiteSetting::find(1);
        $webPath = $setting1->web_logo;
        $oldMobilePath = $setting1->mobile_logo;

        // Replace mobile logo
        $file3 = UploadedFile::fake()->image('mobile2.png', 100, 100);
        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'mobile_logo' => $file3,
        ], $this->authHeaders())->assertStatus(200);

        $setting2 = SiteSetting::find(1);
        $newMobilePath = $setting2->mobile_logo;

        $this->assertNotEquals($oldMobilePath, $newMobilePath);
        $this->assertEquals($webPath, $setting2->web_logo);

        Storage::disk('public')->assertMissing($oldMobilePath);
        Storage::disk('public')->assertExists($newMobilePath);
        Storage::disk('public')->assertExists($webPath);
    }

    // ==========================================
    // 5. NULL / OMITTED SAFETY TESTS
    // ==========================================

    public function test_omitted_logos_retain_existing_logos(): void
    {
        $file = UploadedFile::fake()->image('web.png', 100, 100);
        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'web_logo' => $file,
        ], $this->authHeaders())->assertStatus(200);

        $path = SiteSetting::find(1)->web_logo;

        // Update only text
        $this->patchJson('/api/admin/site-settings', [
            'site_name' => 'Updated Name Only',
        ], $this->authHeaders())->assertStatus(200);

        $fresh = SiteSetting::find(1);
        $this->assertEquals($path, $fresh->web_logo);
        Storage::disk('public')->assertExists($path);
    }

    public function test_null_logo_payload_does_not_remove_existing_file(): void
    {
        $file = UploadedFile::fake()->image('web.png', 100, 100);
        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'web_logo' => $file,
        ], $this->authHeaders())->assertStatus(200);

        $path = SiteSetting::find(1)->web_logo;

        // Sending null web_logo should be ignored or fail validation without mutating disk
        $response = $this->patchJson('/api/admin/site-settings', [
            'web_logo' => null,
        ], $this->authHeaders());

        // Either 422 (because not a file) or 200 with untouched file
        $fresh = SiteSetting::find(1);
        $this->assertEquals($path, $fresh->web_logo);
        Storage::disk('public')->assertExists($path);
    }

    // ==========================================
    // 6. CACHE & SINGLETON INTEGRITY TESTS
    // ==========================================

    public function test_cache_invalidated_on_admin_update(): void
    {
        // Prime public cache
        $this->getJson('/api/site-settings');
        $this->assertTrue(Cache::has(SiteSettingService::PUBLIC_CACHE_KEY));

        // Update via Admin API
        $this->patchJson('/api/admin/site-settings', [
            'site_name' => 'Brand New Name',
        ], $this->authHeaders())->assertStatus(200);

        // Cache must be invalidated
        $this->assertFalse(Cache::has(SiteSettingService::PUBLIC_CACHE_KEY));

        // Next public GET returns fresh updated data
        $this->getJson('/api/site-settings')
            ->assertStatus(200)
            ->assertJsonPath('data.site_name', 'Brand New Name');
    }

    public function test_singleton_integrity_remains_exact_one_row(): void
    {
        $this->patchJson('/api/admin/site-settings', ['site_name' => 'First Update'], $this->authHeaders())->assertStatus(200);
        $this->patchJson('/api/admin/site-settings', ['site_name' => 'Second Update'], $this->authHeaders())->assertStatus(200);
        $this->patchJson('/api/admin/site-settings', ['site_name' => 'Third Update'], $this->authHeaders())->assertStatus(200);

        $this->assertEquals(1, SiteSetting::count());
        $this->assertEquals(1, SiteSetting::first()->id);
    }

    // ==========================================
    // 7. TRANSACTION AND FAILURE SAFETY TESTS
    // ==========================================

    public function test_failed_validation_leaves_storage_untouched(): void
    {
        $file = UploadedFile::fake()->image('web.png', 100, 100);

        $this->post('/api/admin/site-settings', [
            '_method' => 'PATCH',
            'site_name' => '', // Will fail required validation
            'web_logo' => $file,
        ], $this->authHeaders())->assertStatus(422);

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_service_cleans_new_file_if_database_update_throws(): void
    {
        $service = app(SiteSettingService::class);
        $file = UploadedFile::fake()->image('test-failure.png', 100, 100);

        // Temporarily break table query by dropping or triggering mock error
        // We can test by forcing an invalid column or throwing exception in transaction
        try {
            DB::listen(function ($query) {
                if (str_contains($query->sql, 'site_settings') && str_contains($query->sql, 'update')) {
                    throw new \RuntimeException('Forced DB Failure for test');
                }
            });

            $service->updateSettings([
                'site_name' => 'Crash DB',
                'web_logo' => $file,
            ], $this->admin->id);

            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            $this->assertEquals('Forced DB Failure for test', $e->getMessage());
        }

        // All new files should be cleaned up from disk
        $this->assertEmpty(Storage::disk('public')->allFiles('site-settings/logos'));
    }
}
