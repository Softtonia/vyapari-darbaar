<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BusinessCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Spatie\Permission\PermissionRegistrar;

class UserKycApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\EmailTemplateSeeder::class);
        $this->seed(\Database\Seeders\CampaignEmailTemplateSeeder::class);
        $this->seed(\Database\Seeders\CampaignSeeder::class);
    }

    public function test_admin_can_create_trader_with_kyc_and_business_documents()
    {
        Storage::fake('public');

        // Create Admin
        $admin = Admin::forceCreate([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'username' => 'superadmin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_default' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'super_admin', 'slug' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'trader', 'slug' => 'trader', 'guard_name' => 'web']);
        
        $admin->assignRole($role);

        // Create Business Category
        $category = BusinessCategory::create(['name' => 'Retailer', 'status' => 1]);

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.trader@example.com',
            'phone_number' => '9876543210',
            'role' => 'trader',
            'status' => 'active',
            'company_name' => 'John Traders Pvt Ltd',
            'business_type' => 'retail',
            'business_category_ids' => [$category->id],
            
            // Mock File Uploads
            'aadhaar_card' => UploadedFile::fake()->image('aadhaar.jpg'),
            'pan_card' => UploadedFile::fake()->image('pan.jpg'),
            'gst_certificate' => UploadedFile::fake()->create('gst.pdf', 100),
            'business_registration' => UploadedFile::fake()->create('reg.pdf', 100),
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', $payload);

        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'kyc_documents',
                'company' => [
                    'business_documents'
                ]
            ]
        ]);

        $userData = $response->json('data');
        $companyData = $response->json('data.company');

        // Verify Company is created
        $this->assertDatabaseHas('companies', [
            'name' => 'John Traders Pvt Ltd',
            'business_type' => 'retail'
        ]);

        // Verify Documents are linked
        $this->assertNotEmpty($userData['kyc_documents']);
        $this->assertNotEmpty($companyData['business_documents']);

        // Check if files actually "uploaded" in fake storage
        Storage::disk('public')->assertExists($userData['kyc_documents'][0]['file_path']);
    }
}
