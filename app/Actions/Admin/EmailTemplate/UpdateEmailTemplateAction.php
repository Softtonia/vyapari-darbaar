<?php

namespace App\Actions\Admin\EmailTemplate;

use App\Models\EmailTemplate;

class UpdateEmailTemplateAction
{
    /**
     * Update the given email template (excluding key).
     *
     * @param  EmailTemplate  $template
     * @param  array{name: string, subject: string, body: string, is_active?: bool}  $data
     * @return EmailTemplate
     */
    public function execute(EmailTemplate $template, array $data): EmailTemplate
    {
        $updateData = [
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
        ];

        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = (bool) $data['is_active'];
        }

        $template->update($updateData);

        return $template->fresh();
    }
}
