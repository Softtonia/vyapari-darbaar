<?php

namespace App\Actions\Admin\EmailTemplate;

use App\Models\EmailTemplate;

class UpdateEmailTemplateStatusAction
{
    /**
     * Update the active status of an email template.
     */
    public function execute(EmailTemplate $template, bool $isActive): EmailTemplate
    {
        $template->update([
            'is_active' => $isActive,
        ]);

        return $template->fresh();
    }
}
