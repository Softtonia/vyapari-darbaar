<?php

namespace App\Actions\Admin\User;

use App\Jobs\SendUserCredentialsEmailJob;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplateRenderer;
use App\Services\TemporaryPasswordGenerator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResendUserCredentialsAction
{
    public function __construct(
        protected TemporaryPasswordGenerator $passwordGenerator,
        protected EmailTemplateRenderer $templateRenderer
    ) {}

    /**
     * Resend user credentials with a new secure temporary password, preserving username.
     *
     * @param  User  $user
     * @return void
     *
     * @throws HttpResponseException
     */
    public function execute(User $user): void
    {
        // 1. Verify target user is active
        if ($user->status !== 'active') {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'message' => 'Cannot resend credentials to an inactive or suspended user. Please activate the user first.',
                    'error' => 'Invalid user status',
                ], 422)
            );
        }

        // 2. Preflight template
        $template = EmailTemplate::query()
            ->where('key', 'USER_ACCOUNT_CREATED')
            ->first();

        if (! $template || ! $template->is_active) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'message' => 'Account creation template USER_ACCOUNT_CREATED is missing or inactive.',
                    'error' => 'Template configuration error',
                ], 422)
            );
        }

        // 3. Generate new temporary password
        $tempPassword = $this->passwordGenerator->generate(16);

        // 4. Render email snapshot before DB commit
        $replacements = [
            'UserName' => $user->name,
            'Username' => $user->username,
            'TemporaryPassword' => $tempPassword,
        ];

        $renderedSubject = $this->templateRenderer->render($template->subject, $replacements, 'USER_ACCOUNT_CREATED');
        $renderedBody = $this->templateRenderer->render($template->body, $replacements, 'USER_ACCOUNT_CREATED');

        // 5. Update user password, set must_change_password=true, and revoke all tokens inside transaction
        DB::transaction(function () use ($user, $tempPassword) {
            $user->update([
                'password' => Hash::make($tempPassword),
                'must_change_password' => true,
            ]);

            $user->tokens()->delete();
        });

        // 6. Dispatch encrypted credential job after commit
        SendUserCredentialsEmailJob::dispatch(
            $user->id,
            $user->email,
            $renderedSubject,
            $renderedBody
        )->afterCommit();
    }
}
