<?php

namespace App\Actions\Admin\EmailTemplate;

use App\Models\EmailTemplate;

class CreateEmailTemplateAction
{
    /**
     * Create a new email template.
     *
     * @param  array{name: string, key: string, subject: string, body: string, type?: string, is_active?: bool}  $data
     */
    public function execute(array $data): EmailTemplate
    {
        return EmailTemplate::create([
            'name' => $data['name'],
            'key' => strtoupper(trim($data['key'])),
            'subject' => $data['subject'],
            'body' => $data['body'],
            'type' => isset($data['type']) ? strtolower(trim((string) $data['type'])) : 'html',
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
