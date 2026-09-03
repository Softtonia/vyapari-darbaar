<?php

namespace App\Actions\Admin\EmailTemplate;

use App\Models\EmailTemplate;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteEmailTemplateAction
{
    /**
     * Delete the specified email template, protecting system-critical templates.
     *
     * @param  EmailTemplate  $emailTemplate
     * @return void
     *
     * @throws HttpResponseException
     */
    public function execute(EmailTemplate $emailTemplate): void
    {
        if ($emailTemplate->key === 'USER_ACCOUNT_CREATED') {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'message' => 'System template USER_ACCOUNT_CREATED is protected and cannot be deleted.',
                    'error' => 'Protected template',
                ], 422)
            );
        }

        $emailTemplate->delete();
    }
}
