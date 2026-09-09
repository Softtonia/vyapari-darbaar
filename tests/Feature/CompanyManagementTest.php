<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(\Database\Seeders\EmailTemplateSeeder::class);
    }

    public function test_trader_registration_creates_company_and_pivot_association(): void
    {
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp('trader@example.com', 'registration');

        $response = $this->postJson('/api/user/register', [
            'first_name' => 'Vikram',
            'last_name' => 'Singhania',
            'email' => 'trader@example.com',
            'phone_number' => '+919876543210',
            'username' => 'vikram_trader',
            'password' => 'Password#2026',
            'password_confirmation' => 'Password#2026',
            'otp' => $otpData['otp'],
            'role' => 'trader',
            'company_name' => 'Singhania Agro Traders Pvt Ltd',
            'contact_person' => 'Vikram Singhania',
            'business_type' => 'Wholesaler',
            'gstin' => '27ABCDE1234F1Z5',
            'country' => 'India',
            'state' => 'Maharashtra',
            'city' => 'Nagpur',
            'address' => 'Shop 12, APMC Market Yard',
            'commodities_handled' => ['Wheat', 'Soybean', 'Cotton'],
            'buy_sell_preference' => 'both',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'message' => 'User registered successfully.',
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNull($response->json('data.user'));

        // Database assertions for User
        $user = User::where('email', 'trader@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('trader'));

        // Database assertions for Company
        $this->assertDatabaseHas('companies', [
            'name' => 'Singhania Agro Traders Pvt Ltd',
            'contact_person' => 'Vikram Singhania',
            'business_type' => 'Wholesaler',
            'gstin' => '27ABCDE1234F1Z5',
            'city' => 'Nagpur',
            'state' => 'Maharashtra',
            'trade_preference' => 'both',
            'verification_status' => 'pending',
        ]);

        $company = Company::where('name', 'Singhania Agro Traders Pvt Ltd')->firstOrFail();

        // Database assertions for user_has_companies
        $this->assertDatabaseHas('user_has_companies', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role' => 'owner',
            'is_primary' => true,
        ]);

        $this->assertEquals($company->id, $user->company?->id);
    }

    public function test_trader_registration_fails_without_company_name(): void
    {
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp('trader_noname@example.com', 'registration');

        $response = $this->postJson('/api/user/register', [
            'first_name' => 'Vikram',
            'last_name' => 'Singhania',
            'email' => 'trader_noname@example.com',
            'password' => 'Password#2026',
            'password_confirmation' => 'Password#2026',
            'otp' => $otpData['otp'],
            'role' => 'trader',
            // company_name omitted
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name'])
            ->assertJsonPath('errors.company_name.0', 'The company name field is required for trader accounts.');
    }

    public function test_non_trader_registration_does_not_create_company(): void
    {
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp('subscriber@example.com', 'registration');

        $response = $this->postJson('/api/user/register', [
            'first_name' => 'Regular',
            'last_name' => 'Subscriber',
            'email' => 'subscriber@example.com',
            'password' => 'Password#2026',
            'password_confirmation' => 'Password#2026',
            'otp' => $otpData['otp'],
            'role' => 'subscriber',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'subscriber@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('subscriber'));

        $this->assertEquals(0, Company::count());
        $this->assertEquals(0, $user->companies()->count());
        $this->assertNull($user->company);
    }

    public function test_trader_can_view_and_update_own_company_profile(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('trader');

        $company = Company::create([
            'name' => 'Original Traders Ltd',
            'contact_person' => 'Original Person',
            'business_type' => 'Retailer',
            'gstin' => '27ABCDE9999F1Z5',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'trade_preference' => 'buy',
            'verification_status' => 'pending',
            'commodities_handled' => ['Wheat'],
        ]);

        $user->companies()->attach($company->id, ['role' => 'owner', 'is_primary' => true]);

        Sanctum::actingAs($user, ['*']);

        // 1. View Company Profile
        $showResponse = $this->getJson('/api/user/company');
        $showResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $company->id,
                    'name' => 'Original Traders Ltd',
                    'company_name' => 'Original Traders Ltd',
                    'city' => 'Pune',
                    'commodities_handled' => ['Wheat'],
                    'verification_status' => 'pending',
                ],
            ]);

        // 2. Update Company Profile
        $updateResponse = $this->patchJson('/api/user/company', [
            'company_name' => 'Updated Traders Global Ltd',
            'city' => 'Mumbai',
            'commodities_handled' => ['Wheat', 'Rice', 'Maize'],
            'trade_preference' => 'both',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Updated Traders Global Ltd',
                    'city' => 'Mumbai',
                    'commodities_handled' => ['Wheat', 'Rice', 'Maize'],
                    'trade_preference' => 'both',
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Updated Traders Global Ltd',
            'city' => 'Mumbai',
        ]);
    }

    public function test_non_trader_without_company_gets_404_on_company_profile(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/user/company');
        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
                'message' => 'No company associated with this user account.',
            ]);
    }

    public function test_admin_can_list_and_filter_companies(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin_company_test@example.com',
            'password' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $admin->assignRole('admin');
        $adminToken = $admin->createToken('admin-token')->plainTextToken;

        Company::create([
            'name' => 'Maharashtra Cotton Corp',
            'city' => 'Nagpur',
            'state' => 'Maharashtra',
            'verification_status' => 'verified',
        ]);

        Company::create([
            'name' => 'Gujarat Spices Trade',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'verification_status' => 'pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/admin/companies?verification_status=verified');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
            ])
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Maharashtra Cotton Corp');
    }

    public function test_admin_can_update_company_verification_status(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin_status_test@example.com',
            'password' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $admin->assignRole('admin');
        $adminToken = $admin->createToken('admin-token')->plainTextToken;

        $company = Company::create([
            'name' => 'Pending Review Ltd',
            'verification_status' => 'pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->patchJson("/api/admin/companies/{$company->id}/status", [
                'verification_status' => 'verified',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'verification_status' => 'verified',
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'verification_status' => 'verified',
        ]);
    }

    public function test_admin_can_provision_trader_user_with_company(): void
    {
        Queue::fake();

        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin_provision_trader@example.com',
            'password' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $admin->assignRole('admin');
        $adminToken = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/admin/users', [
                'first_name' => 'Manish',
                'last_name' => 'Sharma',
                'phone_number' => '+919876543210',
                'email' => 'manish.trader@example.com',
                'role' => 'trader',
                'company_name' => 'Manish Agro Exports Ltd',
                'contact_person' => 'Manish Sharma',
                'business_type' => 'Exporter',
                'gstin' => '24ABCDE5678F1Z9',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
            ]);

        $response->assertStatus(201);

        $user = User::where('email', 'manish.trader@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('trader'));

        $this->assertDatabaseHas('companies', [
            'name' => 'Manish Agro Exports Ltd',
            'contact_person' => 'Manish Sharma',
            'city' => 'Ahmedabad',
        ]);

        $company = Company::where('name', 'Manish Agro Exports Ltd')->firstOrFail();
        $this->assertDatabaseHas('user_has_companies', [
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);
    }
}
