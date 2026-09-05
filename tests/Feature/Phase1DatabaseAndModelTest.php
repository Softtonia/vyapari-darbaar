<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class Phase1DatabaseAndModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('admins'));
        $expectedColumns = [
            'id',
            'name',
            'email',
            'password',
            'status',
            'last_login_at',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('admins', $column),
                "admins table missing column: {$column}"
            );
        }
    }

    public function test_admin_email_must_be_unique(): void
    {
        Admin::create([
            'name' => 'Admin One',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        Admin::create([
            'name' => 'Admin Two',
            'email' => 'admin@example.com',
            'password' => 'password456',
            'status' => 'active',
        ]);
    }

    public function test_admin_model_uses_sanctum_and_generates_tokens(): void
    {
        $this->assertTrue(in_array(HasApiTokens::class, class_uses_recursive(Admin::class)));

        $admin = Admin::create([
            'name' => 'Admin Token Test',
            'email' => 'admin.token@example.com',
            'password' => 'password123',
        ]);

        $token = $admin->createToken('admin-token');
        $this->assertNotEmpty($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Admin::class,
            'tokenable_id' => $admin->id,
            'name' => 'admin-token',
        ]);
    }

    public function test_users_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $expectedColumns = [
            'id',
            'name',
            'username',
            'email',
            'password',
            'status',
            'must_change_password',
            'created_by_admin_id',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "users table missing column: {$column}"
            );
        }
    }

    public function test_user_username_must_be_unique(): void
    {
        User::create([
            'name' => 'User One',
            'username' => 'johndoe',
            'email' => 'john1@example.com',
            'password' => 'secret123',
        ]);

        $this->expectException(QueryException::class);

        User::create([
            'name' => 'User Two',
            'username' => 'johndoe',
            'email' => 'john2@example.com',
            'password' => 'secret456',
        ]);
    }

    public function test_user_email_must_be_unique(): void
    {
        User::create([
            'name' => 'User One',
            'username' => 'user1',
            'email' => 'duplicate@example.com',
            'password' => 'secret123',
        ]);

        $this->expectException(QueryException::class);

        User::create([
            'name' => 'User Two',
            'username' => 'user2',
            'email' => 'duplicate@example.com',
            'password' => 'secret456',
        ]);
    }

    public function test_user_must_change_password_defaults_to_true(): void
    {
        $user = User::create([
            'name' => 'Default Test',
            'username' => 'defaultuser',
            'email' => 'default@example.com',
            'password' => 'secret123',
        ]);

        $user->refresh();
        $this->assertTrue($user->must_change_password);
    }

    public function test_created_by_admin_foreign_key_sets_null_on_delete(): void
    {
        $admin = Admin::create([
            'name' => 'Creator Admin',
            'email' => 'creator@example.com',
            'password' => 'secret123',
        ]);

        $user = User::create([
            'name' => 'Created User',
            'username' => 'createduser',
            'email' => 'created@example.com',
            'password' => 'secret123',
            'created_by_admin_id' => $admin->id,
        ]);

        $this->assertEquals($admin->id, $user->created_by_admin_id);
        $this->assertEquals($admin->id, $user->creator->id);

        $admin->delete();

        $user->refresh();
        $this->assertNull($user->created_by_admin_id);
        $this->assertNull($user->creator);
    }

    public function test_admin_created_users_relationship(): void
    {
        $admin = Admin::create([
            'name' => 'Manager Admin',
            'email' => 'manager@example.com',
            'password' => 'secret123',
        ]);

        $user1 = User::create([
            'name' => 'User One',
            'username' => 'user1',
            'email' => 'user1@example.com',
            'password' => 'secret123',
            'created_by_admin_id' => $admin->id,
        ]);

        $user2 = User::create([
            'name' => 'User Two',
            'username' => 'user2',
            'email' => 'user2@example.com',
            'password' => 'secret123',
            'created_by_admin_id' => $admin->id,
        ]);

        $this->assertCount(2, $admin->createdUsers);
        $this->assertTrue($admin->createdUsers->contains($user1));
        $this->assertTrue($admin->createdUsers->contains($user2));
    }

    public function test_user_model_uses_sanctum_and_generates_tokens(): void
    {
        $this->assertTrue(in_array(HasApiTokens::class, class_uses_recursive(User::class)));

        $user = User::create([
            'name' => 'Token User',
            'username' => 'tokenuser',
            'email' => 'tokenuser@example.com',
            'password' => 'password123',
        ]);

        $token = $user->createToken('user-token');
        $this->assertNotEmpty($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'user-token',
        ]);
    }

    public function test_no_global_eager_loading_on_models(): void
    {
        $admin = new Admin();
        $user = new User();

        $adminReflection = new \ReflectionClass($admin);
        $adminWithProp = $adminReflection->getProperty('with');
        $adminWithProp->setAccessible(true);
        $this->assertEmpty($adminWithProp->getValue($admin), 'Admin model must not specify global $with');

        $userReflection = new \ReflectionClass($user);
        $userWithProp = $userReflection->getProperty('with');
        $userWithProp->setAccessible(true);
        $this->assertEmpty($userWithProp->getValue($user), 'User model must not specify global $with');
    }

    public function test_passwords_are_hidden_from_serialization(): void
    {
        $admin = Admin::create([
            'name' => 'Serialize Admin',
            'email' => 'seradmin@example.com',
            'password' => 'secretpass',
        ]);

        $user = User::create([
            'name' => 'Serialize User',
            'username' => 'seruser',
            'email' => 'seruser@example.com',
            'password' => 'secretpass',
        ]);

        $adminArray = $admin->toArray();
        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $adminArray);
        $this->assertArrayNotHasKey('password', $userArray);

        $adminJson = $admin->toJson();
        $userJson = $user->toJson();

        $this->assertStringNotContainsString('secretpass', $adminJson);
        $this->assertStringNotContainsString('secretpass', $userJson);
    }

    public function test_email_templates_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('email_templates'));
        $expectedColumns = [
            'id',
            'name',
            'key',
            'subject',
            'body',
            'is_active',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('email_templates', $column),
                "email_templates table missing column: {$column}"
            );
        }
    }

    public function test_email_template_key_must_be_unique(): void
    {
        EmailTemplate::create([
            'name' => 'Template 1',
            'key' => 'TEMPLATE_KEY',
            'subject' => 'Subject 1',
            'body' => 'Body 1',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        EmailTemplate::create([
            'name' => 'Template 2',
            'key' => 'TEMPLATE_KEY',
            'subject' => 'Subject 2',
            'body' => 'Body 2',
            'is_active' => true,
        ]);
    }

    public function test_email_template_seeder_creates_active_template_with_required_placeholders(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $template = EmailTemplate::where('key', 'USER_ACCOUNT_CREATED')->first();

        $this->assertNotNull($template);
        $this->assertTrue($template->is_active);
        $this->assertEquals('New User Account Created', $template->name);
        $this->assertEquals('Your Account Credentials', $template->subject);

        // Required placeholders check
        $this->assertStringContainsString('{{Username}}', $template->body);
        $this->assertStringContainsString('{{TemporaryPassword}}', $template->body);
        $this->assertStringContainsString('{{UserName}}', $template->body);
        $this->assertStringContainsString('{{CompanyName}}', $template->body);
        $this->assertStringContainsString('{{SupportEmail}}', $template->body);
    }

    public function test_admin_seeder_runs_idempotently(): void
    {
        $this->seed(AdminSeeder::class);
        $admin = Admin::where('email', 'admin@vyaparidarbaar.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('password', $admin->password));

        // Re-run seeder to verify idempotency
        $this->seed(AdminSeeder::class);
        $this->assertEquals(1, Admin::where('email', 'admin@vyaparidarbaar.com')->count());
    }

    public function test_composite_index_exists_on_users_table(): void
    {
        $indexes = Schema::getIndexes('users');
        $found = false;
        foreach ($indexes as $index) {
            if ($index['columns'] === ['status', 'created_at', 'id']) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Composite index (status, created_at, id) not found on users table');
    }

    public function test_redis_and_queue_configuration_matches_spec(): void
    {
        $this->assertEquals('phpredis', config('database.redis.client'));
        $this->assertNotNull(config('database.redis.default.database'));
        $this->assertNotNull(config('database.redis.cache.database'));
    }
}
