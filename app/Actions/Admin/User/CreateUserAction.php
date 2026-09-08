<?php

namespace App\Actions\Admin\User;

use App\Jobs\SendUserCredentialsEmailJob;
use App\Models\Admin;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplateRenderer;
use App\Services\TemporaryPasswordGenerator;
use App\Services\UsernameGenerator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function __construct(
        protected UsernameGenerator $usernameGenerator,
        protected TemporaryPasswordGenerator $temporaryPasswordGenerator,
        protected EmailTemplateRenderer $templateRenderer
    ) {}

    /**
     * Provision a new user account, assign Spatie role, render credential email snapshot and queue after commit.
     *
     * @param  Admin  $admin
     * @param  array{first_name: string, last_name: string, name: string, phone_number: string, email: string, role?: string}  $data
     * @return User
     *
     * @throws HttpResponseException
     */
    public function execute(Admin $admin, array $data): User
    {
        // Step 1: Preflight active USER_ACCOUNT_CREATED template
        $template = EmailTemplate::query()
            ->where('key', 'USER_ACCOUNT_CREATED')
            ->first();

        if (! $template || ! $template->is_active) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'message' => 'Account creation template USER_ACCOUNT_CREATED is missing or inactive. User cannot be provisioned.',
                    'error' => 'Template configuration error',
                ], 422)
            );
        }

        // Step 2: Generate unique username server-side with Redis lock
        $username = $this->usernameGenerator->generate($data['name'], $data['email']);

        // Step 3: Generate cryptographically secure temporary password
        $tempPassword = $this->temporaryPasswordGenerator->generate(16);

        // Step 4: Render credential email snapshot before DB commit
        $replacements = [
            'UserName' => $data['name'],
            'Username' => $username,
            'TemporaryPassword' => $tempPassword,
        ];

        $renderedSubject = $this->templateRenderer->render($template->subject, $replacements, 'USER_ACCOUNT_CREATED');
        $renderedBody = $this->templateRenderer->render($template->body, $replacements, 'USER_ACCOUNT_CREATED');

        // Step 5: Persist user and assign Spatie role within DB transaction
        $user = DB::transaction(function () use ($admin, $data, $username, $tempPassword) {
            $newUser = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => $data['name'],
                'phone_number' => $data['phone_number'],
                'username' => $username,
                'email' => $data['email'],
                'password' => Hash::make($tempPassword),
                'status' => 'active',
                'must_change_password' => true,
                'created_by_admin_id' => $admin->id,
            ]);

            $roleName = ! empty($data['role']) ? $data['role'] : 'user';
            
            // Find role by name or slug, default to 'user'
            $role = \App\Models\Role::where('name', $roleName)->orWhere('slug', $roleName)->first();
            $targetRole = $role ? $role->name : 'user';

            // syncRoles handles pivot creation cleanly and prevents duplicate role assignments
            $newUser->syncRoles([$targetRole]);

            return $newUser->fresh(['roles']);
        });

        // Step 6: Dispatch encrypted credential email job after commit
        SendUserCredentialsEmailJob::dispatch(
            $user->id,
            $user->email,
            $renderedSubject,
            $renderedBody
        )->afterCommit();

        return $user;
    }
}
