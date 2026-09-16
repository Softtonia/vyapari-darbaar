<?php

namespace Tests\Feature;

use App\Enums\ExchangeType;
use App\Models\Admin;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Exchange;
use App\Models\ExchangeCommodityMapping;
use App\Services\ExchangeService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExchangeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected Admin $unauthorizedAdmin;

    protected string $unauthorizedAdminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Exchange',
            'last_name' => 'Admin',
            'name' => 'Exchange Admin',
            'email' => 'admin.exchanges@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);

        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        $this->unauthorizedAdmin = Admin::create([
            'first_name' => 'No',
            'last_name' => 'Perms',
            'name' => 'No Perms Admin',
            'email' => 'noperms.exchanges@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);
        $this->unauthorizedAdminToken = $this->unauthorizedAdmin->createToken('no-perm-token')->plainTextToken;
    }

    public function test_admin_with_permission_can_list_exchanges(): void
    {
        Exchange::create([
            'name' => 'National Commodity & Derivatives Exchange Limited',
            'slug' => 'ncdex',
            'code' => 'NCDEX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'timezone' => 'Asia/Kolkata',
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/admin/exchanges');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => ['id', 'name', 'slug', 'code', 'exchange_type', 'timezone', 'status'],
                    ],
                    'pagination',
                ],
            ]);
    }

    public function test_admin_without_permission_cannot_list_exchanges(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->unauthorizedAdminToken)
            ->getJson('/api/admin/exchanges');

        $response->assertStatus(403);
    }

    public function test_admin_can_retrieve_exchange_options(): void
    {
        Exchange::create([
            'name' => 'Multi Commodity Exchange of India Limited',
            'slug' => 'mcx',
            'code' => 'MCX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/admin/exchanges/options');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'code', 'exchange_type'],
                ],
            ]);
    }

    public function test_admin_can_create_exchange_with_normalized_code_and_slug(): void
    {
        $payload = [
            'name' => 'Indian Commodity Exchange',
            'code' => ' icex ',
            'exchange_type' => 'commodity_derivatives',
            'timezone' => 'Asia/Kolkata',
            'default_data_delay_minutes' => 15,
            'sort_order' => 5,
            'status' => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchanges', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.code', 'ICEX')
            ->assertJsonPath('data.slug', 'indian-commodity-exchange')
            ->assertJsonPath('data.default_data_delay_minutes', 15);

        $this->assertDatabaseHas('exchanges', [
            'code' => 'ICEX',
            'slug' => 'indian-commodity-exchange',
            'default_data_delay_minutes' => 15,
        ]);
    }

    public function test_cannot_create_exchange_with_duplicate_code(): void
    {
        Exchange::create([
            'name' => 'NCDEX Limited',
            'slug' => 'ncdex',
            'code' => 'NCDEX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchanges', [
                'name' => 'Another NCDEX',
                'code' => 'NCDEX',
                'exchange_type' => 'commodity_derivatives',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_cannot_create_exchange_with_duplicate_slug(): void
    {
        Exchange::create([
            'name' => 'MCX India',
            'slug' => 'mcx-india',
            'code' => 'MCX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchanges', [
                'name' => 'MCX India Duplicate',
                'slug' => 'mcx-india',
                'code' => 'MCX2',
                'exchange_type' => 'commodity_derivatives',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_can_view_exchange_details(): void
    {
        $exchange = Exchange::create([
            'name' => 'Multi Commodity Exchange of India Limited',
            'slug' => 'mcx',
            'code' => 'MCX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/admin/exchanges/{$exchange->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $exchange->id)
            ->assertJsonPath('data.code', 'MCX');
    }

    public function test_admin_can_update_exchange(): void
    {
        $exchange = Exchange::create([
            'name' => 'Old Exchange Name',
            'slug' => 'old-exchange',
            'code' => 'OLDEX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/admin/exchanges/{$exchange->id}", [
                'name' => 'New Exchange Name',
                'code' => 'NEWEX',
                'exchange_type' => 'commodity_derivatives',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'New Exchange Name')
            ->assertJsonPath('data.code', 'NEWEX');

        $this->assertDatabaseHas('exchanges', [
            'id' => $exchange->id,
            'name' => 'New Exchange Name',
            'code' => 'NEWEX',
        ]);
    }

    public function test_admin_can_toggle_exchange_status(): void
    {
        $exchange = Exchange::create([
            'name' => 'NCDEX Limited',
            'slug' => 'ncdex',
            'code' => 'NCDEX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->patchJson("/api/admin/exchanges/{$exchange->id}/status", [
                'status' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', false);

        $this->assertDatabaseHas('exchanges', [
            'id' => $exchange->id,
            'status' => false,
        ]);
    }

    public function test_admin_can_bulk_update_exchange_status(): void
    {
        $ex1 = Exchange::create([
            'name' => 'Exchange 1',
            'slug' => 'ex-1',
            'code' => 'EX1',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);
        $ex2 = Exchange::create([
            'name' => 'Exchange 2',
            'slug' => 'ex-2',
            'code' => 'EX2',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->patchJson('/api/admin/exchanges/bulk-status', [
                'ids' => [$ex1->id, $ex2->id],
                'status' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.updated_count', 2);

        $this->assertDatabaseHas('exchanges', ['id' => $ex1->id, 'status' => false]);
        $this->assertDatabaseHas('exchanges', ['id' => $ex2->id, 'status' => false]);
    }

    public function test_admin_cannot_delete_exchange_if_mappings_exist(): void
    {
        $exchange = Exchange::create([
            'name' => 'MCX Limited',
            'slug' => 'mcx',
            'code' => 'MCX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $category = CommodityCategory::create([
            'name' => 'Metals',
            'slug' => 'metals',
            'status' => true,
        ]);

        $commodity = Commodity::create([
            'commodity_category_id' => $category->id,
            'name' => 'Gold',
            'slug' => 'gold',
            'code' => 'GOLD',
            'unit' => 'GM',
            'status' => true,
        ]);

        ExchangeCommodityMapping::create([
            'exchange_id' => $exchange->id,
            'commodity_id' => $commodity->id,
            'external_symbol' => 'GOLD',
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/admin/exchanges/{$exchange->id}");

        $response->assertStatus(409)
            ->assertJsonPath('status', false)
            ->assertJsonPath('error', 'EXCHANGE_IN_USE');

        $this->assertDatabaseHas('exchanges', ['id' => $exchange->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_exchange_without_mappings(): void
    {
        $exchange = Exchange::create([
            'name' => 'Removable Exchange',
            'slug' => 'removable',
            'code' => 'REMOVABLE',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/admin/exchanges/{$exchange->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertSoftDeleted('exchanges', ['id' => $exchange->id]);
    }

    public function test_exchange_options_cache_is_invalidated_on_mutations(): void
    {
        Cache::put(ExchangeService::CACHE_KEY_OPTIONS, [['id' => 999, 'name' => 'Cached Exchange']], 3600);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchanges', [
                'name' => 'Fresh Exchange',
                'code' => 'FRESH',
                'exchange_type' => 'commodity_derivatives',
            ]);

        $this->assertFalse(Cache::has(ExchangeService::CACHE_KEY_OPTIONS));
    }
}
