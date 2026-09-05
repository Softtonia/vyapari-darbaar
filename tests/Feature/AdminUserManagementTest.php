<?php

namespace Tests\Feature;

use App\Jobs\SendUserCredentialsEmailJob;
use App\Models\Admin;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\UsernameGenerator;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin-api');
        RateLimiter::clear('admin-user-create');

        $this->seed(EmailTemplateSeeder::class);

        $this->admin = Admin::create([
            'name' => 'Admin Manager',
            'email' => 'admin.manager@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);

        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    public function test_unauthenticated_requests_are_blocked(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
        $this->postJson('/api/admin/users', [])->assertStatus(401);
        $this->getJson('/api/admin/users/1')->assertStatus(401);
        $this->putJson('/api/admin/users/1', [])->assertStatus(401);
        $this->deleteJson('/api/admin/users/1')->assertStatus(401);
    }

    public function test_user_token_is_forbidden_from_admin_user_endpoints(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'username' => 'reg.user',
            'email' => 'reg.user@example.com',
            'password' => Hash::make('Secret123#'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)
            ->getJson('/api/admin/users')
            ->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
    }

    public function test_inactive_admin_is_forbidden(): void
    {
        $this->admin->update(['status' => 'inactive']);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/users')
            ->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
            ]);
    }

    public function test_valid_user_creation_with_server_side_credentials_and_job_dispatch(): void
    {
        Queue::fake();

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/users', [
                'name' => 'Ajay Kumar',
                'email' => 'ajay.kumar@example.com',
                // Privileged and unauthorized fields must be ignored
                'username' => 'custom.username',
                'password' => 'custom.password',
                'status' => 'inactive',
                'must_change_password' => false,
                'created_by_admin_id' => 9999,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'message' => 'User created successfully.',
                'data' => [
                    'name' => 'Ajay Kumar',
                    'username' => 'ajay.kumar',
                    'email' => 'ajay.kumar@example.com',
                    'status' => 'active',
                    'must_change_password' => true,
                ],
            ]);

        $responseData = $response->json('data');
        $this->assertArrayNotHasKey('password', $responseData);
        $this->assertArrayNotHasKey('temporary_password', $responseData);

        $user = User::where('email', 'ajay.kumar@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('ajay.kumar', $user->username);
        $this->assertEquals('active', $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertEquals($this->admin->id, $user->created_by_admin_id);
        $this->assertNotEmpty($user->password);
        $this->assertNotEquals('custom.password', $user->password);

        // Verify queued job
        Queue::assertPushed(
            SendUserCredentialsEmailJob::class,
            function (SendUserCredentialsEmailJob $job) use ($user) {
                $this->assertEquals($user->id, $job->userId);
                $this->assertEquals('ajay.kumar@example.com', $job->recipientEmail);
                $this->assertEquals('redis', $job->connection);
                $this->assertEquals('emails', $job->queue);
                $this->assertInstanceOf(ShouldQueue::class, $job);
                $this->assertInstanceOf(ShouldBeEncrypted::class, $job);

                // Verify snapshot contents
                $this->assertStringContainsString('Ajay Kumar', $job->renderedBody);
                $this->assertStringContainsString('ajay.kumar', $job->renderedBody);
                $this->assertStringNotContainsString('{{Username}}', $job->renderedBody);
                $this->assertStringNotContainsString('{{TemporaryPassword}}', $job->renderedBody);
                $this->assertStringNotContainsString('{{TemporaryPassword}}', $job->renderedSubject);

                return true;
            }
        );
    }

    public function test_username_generation_handles_collisions_with_numeric_suffix(): void
    {
        Queue::fake();

        // Create user 1: Ajay Kumar -> ajay.kumar
        $res1 = $this->withToken($this->adminToken)->postJson('/api/admin/users', [
            'name' => 'Ajay Kumar',
            'email' => 'ajay1@example.com',
        ]);
        $this->assertEquals('ajay.kumar', $res1->json('data.username'));

        // Create user 2: Ajay Kumar -> ajay.kumar2
        $res2 = $this->withToken($this->adminToken)->postJson('/api/admin/users', [
            'name' => 'Ajay Kumar',
            'email' => 'ajay2@example.com',
        ]);
        $this->assertEquals('ajay.kumar2', $res2->json('data.username'));

        // Create user 3: Ajay Kumar -> ajay.kumar3
        $res3 = $this->withToken($this->adminToken)->postJson('/api/admin/users', [
            'name' => 'Ajay Kumar',
            'email' => 'ajay3@example.com',
        ]);
        $this->assertEquals('ajay.kumar3', $res3->json('data.username'));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::create([
            'name' => 'Existing',
            'username' => 'existing.user',
            'email' => 'duplicate@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/users', [
                'name' => 'Another User',
                'email' => 'duplicate@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Validation error.',
            ])
            ->assertJsonValidationErrors(['email']);
    }

    public function test_missing_or_inactive_template_blocks_user_creation(): void
    {
        Queue::fake();

        // Test with inactive template
        $template = EmailTemplate::where('key', 'USER_ACCOUNT_CREATED')->first();
        $template->update(['is_active' => false]);

        $res1 = $this->withToken($this->adminToken)->postJson('/api/admin/users', [
            'name' => 'Blocked User',
            'email' => 'blocked1@example.com',
        ]);

        $res1->assertStatus(422)
            ->assertJson([
                'status' => false,
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'blocked1@example.com']);
        Queue::assertNothingPushed();

        // Test with deleted/missing template
        $template->delete();

        $res2 = $this->withToken($this->adminToken)->postJson('/api/admin/users', [
            'name' => 'Blocked User 2',
            'email' => 'blocked2@example.com',
        ]);

        $res2->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'blocked2@example.com']);
        Queue::assertNothingPushed();
    }

    public function test_job_skips_sending_if_user_was_deleted_before_execution(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Ephemeral User',
            'username' => 'ephemeral.user',
            'email' => 'ephemeral@example.com',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);

        $job = new SendUserCredentialsEmailJob(
            $user->id,
            'ephemeral@example.com',
            'Your Credentials',
            'Body with credentials'
        );

        // Delete user before job runs
        $user->delete();

        // Run job
        $job->handle();

        // Mail should NOT be sent
        Mail::assertNothingSent();
    }

    public function test_job_sends_email_if_user_exists(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Existing User',
            'username' => 'existing.user',
            'email' => 'existing@example.com',
            'password' => Hash::make('secret'),
            'status' => 'active',
        ]);

        $job = new SendUserCredentialsEmailJob(
            $user->id,
            'existing@example.com',
            'Your Credentials',
            'Body with credentials'
        );

        $job->handle();

        Mail::assertSent(\App\Mail\UserCredentialsMail::class, function ($mail) {
            return $mail->hasTo('existing@example.com');
        });
    }

    public function test_user_listing_pagination_and_column_omission(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            User::create([
                'name' => "User {$i}",
                'username' => "user.{$i}",
                'email' => "user{$i}@example.com",
                'password' => Hash::make('password'),
                'status' => 'active',
            ]);
        }

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/users?per_page=2');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Users retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'username',
                            'email',
                            'status',
                            'must_change_password',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'total',
                    'per_page',
                ],
            ]);

        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals(5, $response->json('data.total'));

        // Assert creator relation is NOT loaded
        $firstItem = $response->json('data.data.0');
        $this->assertArrayNotHasKey('creator', $firstItem);
        $this->assertArrayNotHasKey('password', $firstItem);
    }

    public function test_user_listing_filters_and_sorting(): void
    {
        $u1 = User::create([
            'name' => 'Alice Smith',
            'username' => 'alice.smith',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now()->subDays(5),
        ]);

        $u2 = User::create([
            'name' => 'Bob Jones',
            'username' => 'bob.jones',
            'email' => 'bob@example.com',
            'password' => Hash::make('password'),
            'status' => 'inactive',
            'created_at' => now()->subDays(2),
        ]);

        // Search by name substring
        $resName = $this->withToken($this->adminToken)->getJson('/api/admin/users?search=Smith');
        $this->assertCount(1, $resName->json('data.data'));
        $this->assertEquals('Alice Smith', $resName->json('data.data.0.name'));

        // Search by username prefix
        $resUser = $this->withToken($this->adminToken)->getJson('/api/admin/users?search=bob');
        $this->assertCount(1, $resUser->json('data.data'));
        $this->assertEquals('Bob Jones', $resUser->json('data.data.0.name'));

        // Search by email prefix
        $resEmail = $this->withToken($this->adminToken)->getJson('/api/admin/users?search=alice');
        $this->assertCount(1, $resEmail->json('data.data'));
        $this->assertEquals('alice@example.com', $resEmail->json('data.data.0.email'));

        // Status filter
        $resStatus = $this->withToken($this->adminToken)->getJson('/api/admin/users?status=inactive');
        $this->assertCount(1, $resStatus->json('data.data'));
        $this->assertEquals('Bob Jones', $resStatus->json('data.data.0.name'));

        // Sort by name ASC
        $resSort = $this->withToken($this->adminToken)->getJson('/api/admin/users?sort_by=name&sort_dir=asc');
        $this->assertEquals('Alice Smith', $resSort->json('data.data.0.name'));
        $this->assertEquals('Bob Jones', $resSort->json('data.data.1.name'));
    }

    public function test_user_detail_loads_creator_with_only_id_and_name(): void
    {
        $user = User::create([
            'name' => 'Detail User',
            'username' => 'detail.user',
            'email' => 'detail@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_by_admin_id' => $this->admin->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => 'Detail User',
                    'username' => 'detail.user',
                    'email' => 'detail@example.com',
                    'creator' => [
                        'id' => $this->admin->id,
                        'name' => 'Admin Manager',
                    ],
                ],
            ]);

        $creatorData = $response->json('data.creator');
        $this->assertArrayNotHasKey('email', $creatorData);
        $this->assertArrayNotHasKey('password', $creatorData);
    }

    public function test_update_user_name_and_email_with_immutable_username(): void
    {
        $user = User::create([
            'name' => 'Original Name',
            'username' => 'orig.username',
            'email' => 'orig@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        // Attempting to change username fails validation
        $resFail = $this->withToken($this->adminToken)
            ->putJson("/api/admin/users/{$user->id}", [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'username' => 'attempted.new.username',
            ]);

        $resFail->assertStatus(422)
            ->assertJsonValidationErrors(['username']);

        // Valid update without modifying username
        $resSuccess = $this->withToken($this->adminToken)
            ->putJson("/api/admin/users/{$user->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $resSuccess->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                    'username' => 'orig.username',
                ],
            ]);

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('updated@example.com', $user->email);
        $this->assertEquals('orig.username', $user->username);
    }

    public function test_delete_user_hard_deletes_record_and_revokes_tokens(): void
    {
        $user = User::create([
            'name' => 'To Delete',
            'username' => 'to.delete',
            'email' => 'delete@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('active-token')->plainTextToken;
        $this->assertEquals(1, $user->tokens()->count());

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'User deleted successfully.',
            ]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_admin_user_create_rate_limiter(): void
    {
        Queue::fake();

        for ($i = 1; $i <= 20; $i++) {
            $this->withToken($this->adminToken)->postJson('/api/admin/users', [
                'name' => "Batch User {$i}",
                'email' => "batch{$i}@example.com",
            ])->assertStatus(201);
        }

        // 21st attempt is throttled
        $response = $this->withToken($this->adminToken)->postJson('/api/admin/users', [
            'name' => 'Throttled User',
            'email' => 'throttled@example.com',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'status' => false,
                'message' => 'Too many user creation requests. Please try again later.',
            ]);
    }

    public function test_bulk_delete_users_successfully(): void
    {
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $user = User::create([
                'name' => "User {$i}",
                'username' => "bulkuser{$i}",
                'email' => "bulkuser{$i}@example.com",
                'password' => Hash::make('password'),
                'status' => 'active',
                'must_change_password' => false,
            ]);
            $user->createToken('test-token');
            $users[] = $user;
        }

        $ids = array_map(fn ($u) => $u->id, $users);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/users/bulk-delete', [
                'ids' => $ids,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => '3 users deleted successfully.',
                'data' => [
                    'deleted_count' => 3,
                ],
            ]);

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('users', ['id' => $id]);
            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_type' => User::class,
                'tokenable_id' => $id,
            ]);
        }
    }

    public function test_bulk_delete_users_validation_fails_for_invalid_ids(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/users/bulk-delete', [
                'ids' => [99999, 99998],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0', 'ids.1']);
    }

    public function test_bulk_delete_users_validation_fails_for_empty_selection(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/users/bulk-delete', [
                'ids' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);
    }
}
