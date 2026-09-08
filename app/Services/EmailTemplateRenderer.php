<?php

namespace App\Services;

class EmailTemplateRenderer
{
    /**
     * Detailed metadata for placeholders including tag syntax, description, and sample usage.
     *
     * @var array<string, array{tag: string, variable: string, label: string, description: string, example: string}>
     */
    protected static array $placeholderDefinitions = [
        'UserName' => [
            'variable' => 'UserName',
            'tag' => '{{UserName}}',
            'label' => 'User Full Name',
            'description' => 'The full name of the user receiving the email.',
            'example' => 'Demo User',
        ],
        'Username' => [
            'variable' => 'Username',
            'tag' => '{{Username}}',
            'label' => 'Username',
            'description' => 'The unique username / login ID assigned to the user.',
            'example' => 'demo.user',
        ],
        'TemporaryPassword' => [
            'variable' => 'TemporaryPassword',
            'tag' => '{{TemporaryPassword}}',
            'label' => 'Temporary Password',
            'description' => 'The system-generated temporary password for initial login.',
            'example' => 'TempExample123!',
        ],
        'CompanyName' => [
            'variable' => 'CompanyName',
            'tag' => '{{CompanyName}}',
            'label' => 'Company / Application Name',
            'description' => 'The configured organization or application brand name.',
            'example' => 'Vyapari Darbaar',
        ],
        'SupportEmail' => [
            'variable' => 'SupportEmail',
            'tag' => '{{SupportEmail}}',
            'label' => 'Support Email',
            'description' => 'The official support contact email address.',
            'example' => 'support@vyaparidarbaar.com',
        ],
    ];

    /**
     * Map of allowed placeholders per template key.
     *
     * @var array<string, list<string>>
     */
    protected static array $allowedPlaceholders = [
        'USER_ACCOUNT_CREATED' => [
            'UserName',
            'Username',
            'TemporaryPassword',
            'CompanyName',
            'SupportEmail',
        ],
    ];

    /**
     * Global placeholders available to all templates.
     *
     * @var list<string>
     */
    protected static array $globalPlaceholders = [
        'CompanyName',
        'SupportEmail',
    ];

    /**
     * Render the given text by safely replacing allowed placeholders.
     *
     * @param  string  $text
     * @param  array<string, string>  $replacements
     * @param  string|null  $templateKey
     * @return string
     */
    public function render(string $text, array $replacements, ?string $templateKey = null): string
    {
        $allowed = $this->getAllowedPlaceholders($templateKey);
        $globalValues = $this->getGlobalReplacements();
        $mergedReplacements = array_merge($globalValues, $replacements);

        $search = [];
        $replace = [];

        foreach ($allowed as $placeholder) {
            $search[] = '{{'.$placeholder.'}}';
            $replace[] = $mergedReplacements[$placeholder] ?? '';
        }

        return str_replace($search, $replace, $text);
    }

    /**
     * Get preview render using safe demo values.
     *
     * @param  string  $templateKey
     * @param  string  $subject
     * @param  string  $body
     * @return array{subject: string, body: string}
     */
    public function preview(string $templateKey, string $subject, string $body): array
    {
        $demoData = $this->getPreviewDemoData($templateKey);

        return [
            'subject' => $this->render($subject, $demoData, $templateKey),
            'body' => $this->render($body, $demoData, $templateKey),
        ];
    }

    /**
     * Get allowed placeholders for a specific template key.
     *
     * @param  string|null  $templateKey
     * @return list<string>
     */
    public function getAllowedPlaceholders(?string $templateKey = null): array
    {
        if ($templateKey && isset(self::$allowedPlaceholders[$templateKey])) {
            return self::$allowedPlaceholders[$templateKey];
        }

        return self::$globalPlaceholders;
    }

    /**
     * Get allowed placeholders with full descriptions, tags, and usage examples.
     *
     * @param  string|null  $templateKey
     * @return list<array{variable: string, tag: string, label: string, description: string, example: string}>
     */
    public function getPlaceholdersWithMetadata(?string $templateKey = null): array
    {
        $allowed = $this->getAllowedPlaceholders($templateKey);
        $result = [];

        foreach ($allowed as $key) {
            if (isset(self::$placeholderDefinitions[$key])) {
                $result[] = self::$placeholderDefinitions[$key];
            } else {
                $result[] = [
                    'variable' => $key,
                    'tag' => '{{'.$key.'}}',
                    'label' => $key,
                    'description' => "Dynamic value for {$key}",
                    'example' => '',
                ];
            }
        }

        return $result;
    }

    /**
     * Get global values configured for the application.
     *
     * @return array<string, string>
     */
    protected function getGlobalReplacements(): array
    {
        return [
            'CompanyName' => (string) config('app.name', 'Vyapari Darbaar'),
            'SupportEmail' => (string) config('app.support_email', config('mail.from.address', 'support@vyaparidarbaar.com')),
        ];
    }

    /**
     * Get safe demo replacement values for previewing.
     *
     * @param  string  $templateKey
     * @return array<string, string>
     */
    protected function getPreviewDemoData(string $templateKey): array
    {
        $data = $this->getGlobalReplacements();

        if ($templateKey === 'USER_ACCOUNT_CREATED') {
            $data['UserName'] = 'Demo User';
            $data['Username'] = 'demo.user';
            $data['TemporaryPassword'] = 'TempExample123!';
        }

        return $data;
    }
}
