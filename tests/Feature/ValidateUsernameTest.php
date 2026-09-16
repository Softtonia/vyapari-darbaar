<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ValidateUsernameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_valid_and_available_username_returns_success(): void
    {
        $response = $this->postJson('/api/user/validate-username', [
            'username' => 'ramesh.kumar99',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => "The username 'ramesh.kumar99' is available.",
                'data' => [
                    'username' => 'ramesh.kumar99',
                    'is_available' => true,
                ],
            ]);

        $this->assertArrayNotHasKey('available', $response->json('data'));
    }

    public function test_existing_username_returns_validation_error(): void
    {
        User::create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing@example.com',
            'username' => 'existing.trader',
            'password' => Hash::make('Password@123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/user/validate-username', [
            'username' => 'existing.trader',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username'])
            ->assertJsonPath('errors.username.0', 'This username is already taken. Please try another one.');
    }

    public function test_reserved_username_returns_validation_error(): void
    {
        $response = $this->postJson('/api/user/validate-username', [
            'username' => 'admin',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username'])
            ->assertJsonPath('errors.username.0', 'This username is reserved. Please choose a different one.');
    }

    public function test_short_username_returns_validation_error(): void
    {
        $response = $this->postJson('/api/user/validate-username', [
            'username' => 'ab',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username'])
            ->assertJsonPath('errors.username.0', 'Username must be at least 3 characters long.');
    }

    public function test_invalid_characters_or_symbols_at_boundary_returns_validation_error(): void
    {
        // Leading dot
        $response1 = $this->postJson('/api/user/validate-username', [
            'username' => '.invalidname',
        ]);
        $response1->assertStatus(422)->assertJsonValidationErrors(['username']);

        // Trailing underscore
        $response2 = $this->postJson('/api/user/validate-username', [
            'username' => 'invalidname_',
        ]);
        $response2->assertStatus(422)->assertJsonValidationErrors(['username']);

        // Disallowed special chars
        $response3 = $this->postJson('/api/user/validate-username', [
            'username' => 'invalid@user#',
        ]);
        $response3->assertStatus(422)->assertJsonValidationErrors(['username']);

        // Consecutive dots
        $response4 = $this->postJson('/api/user/validate-username', [
            'username' => 'invalid..user',
        ]);
        $response4->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    public function test_missing_username_field_returns_validation_error(): void
    {
        $response = $this->postJson('/api/user/validate-username', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username'])
            ->assertJsonPath('errors.username.0', 'Please enter a username.');
    }
}
