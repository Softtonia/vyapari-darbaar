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
     * Provision a new user account, render credential email snapshot and queue after commit.
     *
     * @param  Admin  $admin
     * @param  array{name: string, email: string}  $data
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

        // Step 5: Persist user within DB transaction
        $user = DB::transaction(function () use ($admin, $data, $username, $tempPassword) {
            return User::create([
                'name' => $data['name'],
                'username' => $username,
                'email' => $data['email'],
                'password' => Hash::make($tempPassword),
                'status' => 'active',
                'must_change_password' => true,
                'created_by_admin_id' => $admin->id,
            ]);
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
