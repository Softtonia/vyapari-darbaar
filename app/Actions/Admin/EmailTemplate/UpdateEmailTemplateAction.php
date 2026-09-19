<?php

namespace App\Actions\Admin\EmailTemplate;

use App\Models\EmailTemplate;

class UpdateEmailTemplateAction
{
    /**
     * Update the given email template (excluding key).
     *
     * @param  array{name: string, subject: string, body: string, type?: string, is_active?: bool}  $data
     */
    public function execute(EmailTemplate $template, array $data): EmailTemplate
    {
        $updateData = [
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
        ];

        if (array_key_exists('type', $data)) {
            $updateData['type'] = strtolower(trim((string) $data['type']));
        }

        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = (bool) $data['is_active'];
        }

        $template->update($updateData);

        return $template->fresh();
    }
}
