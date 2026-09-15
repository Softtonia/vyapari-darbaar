<?php

namespace Tests\Feature;

use App\Enums\MarketType;
use App\Models\District;
use App\Models\Mandi;
use App\Models\State;
use App\Services\DistrictService;
use App\Services\MandiService;
use App\Services\StateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicLocationTest extends TestCase
{
    use RefreshDatabase;

    protected State $activeState;

    protected State $inactiveState;

    protected District $activeDistrict;

    protected District $inactiveDistrict;

    protected District $districtUnderInactiveState;

    protected Mandi $activeMandi;

    protected Mandi $inactiveMandi;

    protected Mandi $mandiUnderInactiveDistrict;

    protected Mandi $mandiUnderInactiveState;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(StateService::CACHE_KEY_OPTIONS);

        // 1. Active hierarchy
        $this->activeState = State::create(['name' => 'Maharashtra', 'slug' => 'maharashtra', 'code' => 'MH', 'status' => true]);
        $this->activeDistrict = District::create(['state_id' => $this->activeState->id, 'name' => 'Nagpur', 'slug' => 'nagpur', 'code' => 'NGP', 'status' => true]);
        $this->inactiveDistrict = District::create(['state_id' => $this->activeState->id, 'name' => 'Akola', 'slug' => 'akola', 'code' => 'AKL', 'status' => false]);
        $this->activeMandi = Mandi::create([
            'district_id' => $this->activeDistrict->id,
            'name' => 'Nagpur APMC Mandi',
            'slug' => 'nagpur-apmc-mandi',
            'code' => 'NGP-APMC',
            'market_type' => MarketType::APMC->value,
            'status' => true,
        ]);
        $this->inactiveMandi = Mandi::create([
            'district_id' => $this->activeDistrict->id,
            'name' => 'Nagpur Sub Yard',
            'slug' => 'nagpur-sub-yard',
            'code' => 'NGP-SUB',
            'market_type' => MarketType::SUB_YARD->value,
            'status' => false,
        ]);
        $this->mandiUnderInactiveDistrict = Mandi::create([
            'district_id' => $this->inactiveDistrict->id,
            'name' => 'Akola Mandi',
            'slug' => 'akola-mandi',
            'code' => 'AKL-MND',
            'market_type' => MarketType::APMC->value,
            'status' => true,
        ]);

        // 2. Inactive parent state hierarchy
        $this->inactiveState = State::create(['name' => 'Inactive State', 'slug' => 'inactive-state', 'code' => 'IS', 'status' => false]);
        $this->districtUnderInactiveState = District::create(['state_id' => $this->inactiveState->id, 'name' => 'District X', 'slug' => 'district-x', 'code' => 'DX', 'status' => true]);
        $this->mandiUnderInactiveState = Mandi::create([
            'district_id' => $this->districtUnderInactiveState->id,
            'name' => 'Mandi X',
            'slug' => 'mandi-x',
            'code' => 'MX',
            'market_type' => MarketType::APMC->value,
            'status' => true,
        ]);

        Cache::forget(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->activeState->id);
        Cache::forget(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->inactiveState->id);
        Cache::forget(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->activeDistrict->id);
        Cache::forget(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->inactiveDistrict->id);
        Cache::forget(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->activeState->id);
        Cache::forget(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->inactiveState->id);
    }

    public function test_public_states_returns_active_non_deleted_states_only(): void
    {
        $response = $this->getJson('/api/locations/states')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'code'],
                ],
            ]);

        $this->assertTrue($response->json('status'));
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Maharashtra', $data[0]['name']);

        // Check no internal audit fields
        $this->assertArrayNotHasKey('created_by', $data[0]);
        $this->assertArrayNotHasKey('updated_by', $data[0]);
    }

    public function test_public_districts_filtered_by_active_state(): void
    {
        $response = $this->getJson("/api/locations/districts?state_id={$this->activeState->id}")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Nagpur', $data[0]['name']);
    }

    public function test_public_districts_returns_empty_if_parent_state_inactive(): void
    {
        $response = $this->getJson("/api/locations/districts?state_id={$this->inactiveState->id}")
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
    }

    public function test_public_districts_without_filter_returns_only_districts_with_active_parents(): void
    {
        $response = $this->getJson('/api/locations/districts')
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Nagpur', $data[0]['name']);
    }

    public function test_public_mandis_filtered_by_district(): void
    {
        $response = $this->getJson("/api/locations/mandis?district_id={$this->activeDistrict->id}")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Nagpur APMC Mandi', $data[0]['name']);
        $this->assertEquals('apmc', $data[0]['market_type']);

        // Check no internal audit fields
        $this->assertArrayNotHasKey('created_by', $data[0]);
        $this->assertArrayNotHasKey('updated_by', $data[0]);
    }

    public function test_public_mandis_hides_mandis_if_district_is_inactive(): void
    {
        $response = $this->getJson("/api/locations/mandis?district_id={$this->inactiveDistrict->id}")
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
    }

    public function test_public_mandis_filtered_by_state(): void
    {
        $response = $this->getJson("/api/locations/mandis?state_id={$this->activeState->id}")
            ->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Nagpur APMC Mandi', $data[0]['name']);
    }

    public function test_public_mandis_hides_mandis_if_state_is_inactive(): void
    {
        $response = $this->getJson("/api/locations/mandis?state_id={$this->inactiveState->id}")
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
    }

    public function test_public_mandis_rejects_conflicting_state_and_district_with_422(): void
    {
        // activeDistrict belongs to activeState, not inactiveState
        $this->getJson("/api/locations/mandis?state_id={$this->inactiveState->id}&district_id={$this->activeDistrict->id}")
            ->assertStatus(422)
            ->assertJson([
                'status' => false,
            ]);
    }

    public function test_soft_deleted_parents_hide_descendants_in_public_api(): void
    {
        // Soft delete active state
        $this->activeState->delete();

        // Districts under it should now be empty in public
        $resDist = $this->getJson("/api/locations/districts?state_id={$this->activeState->id}")->assertStatus(200);
        $this->assertCount(0, $resDist->json('data'));

        // Mandis under it should now be empty in public
        $resMandi = $this->getJson("/api/locations/mandis?district_id={$this->activeDistrict->id}")->assertStatus(200);
        $this->assertCount(0, $resMandi->json('data'));
    }
}
