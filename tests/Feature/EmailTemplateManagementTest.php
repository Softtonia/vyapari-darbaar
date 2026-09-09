<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EmailTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin-api');

        $this->admin = Admin::create([
            'name' => 'Template Admin',
            'email' => 'tpl.admin@example.com',
            'password' => Hash::make('Password#2026'),
            'status' => 'active',
        ]);

        $this->adminToken = $this->admin->createToken('admin-test')->plainTextToken;
    }

    public function test_unauthenticated_request_cannot_access_template_endpoints(): void
    {
        $this->getJson('/api/admin/email-templates')->assertStatus(401);
        $this->postJson('/api/admin/email-templates', [])->assertStatus(401);
        $this->postJson('/api/admin/email-templates/preview', [])->assertStatus(401);
    }

    public function test_user_sanctum_token_is_forbidden(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'username' => 'reguser',
            'email' => 'reguser@example.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)
            ->getJson('/api/admin/email-templates')
            ->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
    }

    public function test_inactive_admin_token_is_forbidden(): void
    {
        $this->admin->update(['status' => 'inactive']);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/email-templates')
            ->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
            ]);
    }

    public function test_active_admin_can_list_templates_with_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            EmailTemplate::create([
                'name' => "Template {$i}",
                'key' => "CUSTOM_TEMPLATE_{$i}",
                'subject' => "Subject {$i}",
                'body' => "Body content {$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/email-templates?per_page=2');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Email templates retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'key',
                            'subject',
                            'is_active',
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
    }

    public function test_template_list_omits_body_column_for_performance(): void
    {
        EmailTemplate::create([
            'name' => 'Big Body Template',
            'key' => 'BIG_BODY_TEMPLATE',
            'subject' => 'Subject',
            'body' => 'Very heavy body content that should not be in list',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/email-templates');

        $response->assertStatus(200);
        $items = $response->json('data.data');

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertArrayNotHasKey('body', $item, 'List endpoint must not include body column');
        }
    }

    public function test_template_detail_returns_body_column(): void
    {
        $template = EmailTemplate::create([
            'name' => 'Detail Test',
            'key' => 'DETAIL_TEST_KEY',
            'subject' => 'Detail Subject',
            'body' => 'Full detail body text here',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/admin/email-templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $template->id,
                    'name' => 'Detail Test',
                    'key' => 'DETAIL_TEST_KEY',
                    'subject' => 'Detail Subject',
                    'body' => 'Full detail body text here',
                    'is_active' => true,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'supported_placeholders' => [
                        '*' => ['variable', 'tag', 'label', 'description', 'example'],
                    ],
                ],
            ]);
    }

    public function test_get_by_key_returns_expected_template(): void
    {
        EmailTemplate::create([
            'name' => 'Key Lookup Test',
            'key' => 'SPECIFIC_LOOKUP_KEY',
            'subject' => 'Key Subject',
            'body' => 'Key Body',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/email-templates/by-key/SPECIFIC_LOOKUP_KEY');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'key' => 'SPECIFIC_LOOKUP_KEY',
                    'name' => 'Key Lookup Test',
                    'body' => 'Key Body',
                ],
            ]);

        // Nonexistent key returns 404
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/email-templates/by-key/NON_EXISTENT_KEY')
            ->assertStatus(404);
    }

    public function test_create_valid_email_template(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/email-templates', [
                'name' => 'Order Notification',
                'key' => 'ORDER_NOTIFICATION',
                'subject' => 'Your order has shipped',
                'body' => 'Hello, your order is on the way from {{CompanyName}}.',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'message' => 'Email template created successfully.',
                'data' => [
                    'name' => 'Order Notification',
                    'key' => 'ORDER_NOTIFICATION',
                    'subject' => 'Your order has shipped',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('email_templates', [
            'key' => 'ORDER_NOTIFICATION',
            'name' => 'Order Notification',
        ]);
    }

    public function test_duplicate_key_is_rejected(): void
    {
        EmailTemplate::create([
            'name' => 'Original',
            'key' => 'DUPLICATE_KEY_TEST',
            'subject' => 'Sub',
            'body' => 'Body',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/email-templates', [
                'name' => 'Duplicate Attempt',
                'key' => 'DUPLICATE_KEY_TEST',
                'subject' => 'Sub 2',
                'body' => 'Body 2',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Validation error.',
            ]);
    }

    public function test_invalid_key_format_is_rejected(): void
    {
        $invalidKeys = [
            'lowercase_key',
            'Key With Spaces',
            'KEY-WITH-DASHES',
            'KEY_WITH_SPECIAL_$',
        ];

        foreach ($invalidKeys as $invalidKey) {
            $response = $this->withToken($this->adminToken)
                ->postJson('/api/admin/email-templates', [
                    'name' => 'Test',
                    'key' => $invalidKey,
                    'subject' => 'Sub',
                    'body' => 'Body',
                ]);

            $response->assertStatus(422)
                ->assertJson([
                    'status' => false,
                    'message' => 'Validation error.',
                ]);
        }
    }

    public function test_template_key_is_immutable_on_update(): void
    {
        $template = EmailTemplate::create([
            'name' => 'Immutable Test',
            'key' => 'IMMUTABLE_KEY',
            'subject' => 'Old Subject',
            'body' => 'Old Body',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/admin/email-templates/{$template->id}", [
                'name' => 'Updated Name',
                'key' => 'TRYING_TO_MUTATE_KEY',
                'subject' => 'Updated Subject',
                'body' => 'Updated Body',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Validation error.',
            ]);

        $template->refresh();
        $this->assertEquals('IMMUTABLE_KEY', $template->key);
    }

    public function test_update_name_subject_body_works(): void
    {
        $template = EmailTemplate::create([
            'name' => 'Old Name',
            'key' => 'EDITABLE_TEST',
            'subject' => 'Old Subject',
            'body' => 'Old Body',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/admin/email-templates/{$template->id}", [
                'name' => 'New Name',
                'subject' => 'New Subject',
                'body' => 'New Body Content with {{CompanyName}}',
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'New Name',
                    'key' => 'EDITABLE_TEST',
                    'subject' => 'New Subject',
                    'body' => 'New Body Content with {{CompanyName}}',
                    'is_active' => false,
                ],
            ]);

        $template->refresh();
        $this->assertEquals('New Name', $template->name);
        $this->assertFalse($template->is_active);
    }

    public function test_update_status_endpoint(): void
    {
        $template = EmailTemplate::create([
            'name' => 'Status Test',
            'key' => 'STATUS_TEST',
            'subject' => 'Subject',
            'body' => 'Body',
            'is_active' => true,
        ]);

        // Deactivate
        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/email-templates/{$template->id}/status", [
                'is_active' => false,
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'is_active' => false,
                ],
            ]);

        $this->assertFalse($template->fresh()->is_active);

        // Reactivate
        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/email-templates/{$template->id}/status", [
                'is_active' => true,
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'is_active' => true,
                ],
            ]);

        $this->assertTrue($template->fresh()->is_active);
    }

    public function test_preview_endpoint_renders_placeholders_without_persisting(): void
    {
        $initialCount = EmailTemplate::count();

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/email-templates/preview', [
                'key' => 'USER_ACCOUNT_CREATED',
                'subject' => 'Welcome to {{CompanyName}}',
                'body' => 'Hello {{UserName}}, your username is {{Username}} and temp password is {{TemporaryPassword}}. Contact {{SupportEmail}}.',
            ]);

        $expectedCompanyName = (string) config('app.name', 'Vyapari Darbar');
        $expectedSupportEmail = (string) config('app.support_email', config('mail.from.address', 'support@vyaparidarbar.com'));

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Preview generated successfully.',
                'data' => [
                    'subject' => "Welcome to {$expectedCompanyName}",
                ],
            ]);

        $renderedBody = $response->json('data.body');
        $this->assertStringContainsString('Demo User', $renderedBody);
        $this->assertStringContainsString('demo.user', $renderedBody);
        $this->assertStringContainsString('TempExample123!', $renderedBody);
        $this->assertStringContainsString($expectedSupportEmail, $renderedBody);

        // Database was NOT modified
        $this->assertEquals($initialCount, EmailTemplate::count());
    }

    public function test_unknown_placeholder_is_not_executed(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/email-templates/preview', [
                'key' => 'CUSTOM_TEMPLATE',
                'subject' => 'Subject {{UnknownVariable}}',
                'body' => 'Body with {{UnknownPlaceholder}} and {{phpinfo()}} and {{system("dir")}}',
            ]);

        $response->assertStatus(200);
        $body = $response->json('data.body');
        $this->assertStringContainsString('{{UnknownPlaceholder}}', $body);
        $this->assertStringContainsString('{{phpinfo()}}', $body);
    }

    public function test_user_account_created_requires_username_and_temporary_password_in_body(): void
    {
        // Missing TemporaryPassword
        $res1 = $this->withToken($this->adminToken)
            ->postJson('/api/admin/email-templates', [
                'name' => 'New User Account',
                'key' => 'USER_ACCOUNT_CREATED',
                'subject' => 'Credentials',
                'body' => 'Welcome {{Username}}, password is not included.',
            ]);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        // Missing Username
        $res2 = $this->withToken($this->adminToken)
            ->postJson('/api/admin/email-templates', [
                'name' => 'New User Account',
                'key' => 'USER_ACCOUNT_CREATED',
                'subject' => 'Credentials',
                'body' => 'Welcome user, temporary password is {{TemporaryPassword}}.',
            ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_temporary_password_is_forbidden_in_subject(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/email-templates', [
                'name' => 'Leaky Subject',
                'key' => 'CUSTOM_KEY_ONE',
                'subject' => 'Your password is {{TemporaryPassword}}',
                'body' => 'Hello user.',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject']);
    }

    public function test_seeded_user_account_created_template_exists_and_active(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $template = EmailTemplate::where('key', 'USER_ACCOUNT_CREATED')->first();
        $this->assertNotNull($template);
        $this->assertTrue($template->is_active);
        $this->assertStringContainsString('{{Username}}', $template->body);
        $this->assertStringContainsString('{{TemporaryPassword}}', $template->body);
    }

    public function test_delete_email_template_successfully(): void
    {
        $template = EmailTemplate::create([
            'name' => 'Custom Template',
            'key' => 'CUSTOM_NOTIFICATION',
            'subject' => 'Notification Subject',
            'body' => 'Notification Body',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/email-templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Email template deleted successfully.',
            ]);

        $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
    }

    public function test_protected_system_template_cannot_be_deleted(): void
    {
        $template = EmailTemplate::create([
            'name' => 'System Template',
            'key' => 'USER_ACCOUNT_CREATED',
            'subject' => 'Account Credentials',
            'body' => 'Username: {{Username}}, Password: {{TemporaryPassword}}',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/email-templates/{$template->id}");

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'System template USER_ACCOUNT_CREATED is protected and cannot be deleted.',
            ]);

        $this->assertDatabaseHas('email_templates', ['id' => $template->id]);
    }

    public function test_bulk_delete_email_templates_successfully(): void
    {
        $templates = [];
        for ($i = 1; $i <= 3; $i++) {
            $templates[] = EmailTemplate::create([
                'name' => "Template {$i}",
                'key' => "TEMPLATE_KEY_{$i}",
                'subject' => "Subject {$i}",
                'body' => "Body {$i}",
                'is_active' => true,
            ]);
        }

        $ids = array_map(fn ($t) => $t->id, $templates);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/email-templates/bulk-delete', [
                'ids' => $ids,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => '3 email templates deleted successfully.',
                'data' => [
                    'deleted_count' => 3,
                ],
            ]);

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('email_templates', ['id' => $id]);
        }
    }

    public function test_bulk_delete_rejects_selection_containing_protected_template(): void
    {
        $systemTemplate = EmailTemplate::create([
            'name' => 'System Template',
            'key' => 'USER_ACCOUNT_CREATED',
            'subject' => 'Account Credentials',
            'body' => 'Username: {{Username}}, Password: {{TemporaryPassword}}',
            'is_active' => true,
        ]);

        $customTemplate = EmailTemplate::create([
            'name' => 'Custom Template',
            'key' => 'CUSTOM_TEMPLATE_DEL',
            'subject' => 'Subject',
            'body' => 'Body',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/email-templates/bulk-delete', [
                'ids' => [$systemTemplate->id, $customTemplate->id],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'The selection includes system template USER_ACCOUNT_CREATED which is protected and cannot be deleted.',
            ]);

        $this->assertDatabaseHas('email_templates', ['id' => $systemTemplate->id]);
        $this->assertDatabaseHas('email_templates', ['id' => $customTemplate->id]);
    }

    public function test_admin_can_retrieve_placeholders_list(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/email-templates/placeholders');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Supported email template placeholders retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'variable',
                        'tag',
                        'label',
                        'description',
                        'example',
                    ],
                ],
            ]);

        // Key specific lookup
        $resKey = $this->withToken($this->adminToken)
            ->getJson('/api/admin/email-templates/placeholders?key=USER_ACCOUNT_CREATED');

        $resKey->assertStatus(200);
        $this->assertCount(5, $resKey->json('data'));
    }
}
