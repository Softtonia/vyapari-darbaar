<?php

namespace App\Actions\Admin\EmailTemplate;

use App\Services\EmailTemplateRenderer;

class PreviewEmailTemplateAction
{
    public function __construct(
        protected EmailTemplateRenderer $renderer
    ) {}

    /**
     * Preview rendered subject and body with demo placeholder values.
     *
     * @param  string  $key
     * @param  string  $subject
     * @param  string  $body
     * @return array{subject: string, body: string}
     */
    public function execute(string $key, string $subject, string $body): array
    {
        return $this->renderer->preview($key, $subject, $body);
    }
}
