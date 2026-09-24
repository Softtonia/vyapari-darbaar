<?php

namespace App\Actions\Admin\EmailTemplate;

use App\Models\EmailTemplate;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteEmailTemplateAction
{
    /**
     * Delete the specified email template, protecting system-critical templates.
     *
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

        try {
            $emailTemplate->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            // Check if it's a foreign key constraint violation (usually code 23000)
            if ($e->getCode() == '23000') {
                throw new HttpResponseException(
                    response()->json([
                        'status' => false,
                        'message' => 'Cannot delete this template because it is currently assigned to one or more campaigns.',
                        'error' => 'Foreign key constraint',
                    ], 400)
                );
            }
            throw $e;
        }
    }
}
