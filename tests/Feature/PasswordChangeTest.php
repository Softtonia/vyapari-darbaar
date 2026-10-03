<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;

class PasswordChangeTest extends TestCase
{
    public function test_change_password()
    {
        $admin = User::find(1);
        
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/change-password', [
            'current_password' => 'password123',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);
        
        dd($response->json(), $response->status());
    }
}
