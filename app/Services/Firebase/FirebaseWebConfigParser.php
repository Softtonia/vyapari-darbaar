<?php

namespace App\Services\Firebase;

class FirebaseWebConfigParser
{
    /**
     * Map of normalized database field names to accepted alias keys in camelCase and snake_case.
     */
    protected const FIELD_ALIASES = [
        'api_key' => ['apiKey', 'api_key'],
        'auth_domain' => ['authDomain', 'auth_domain'],
        'project_id' => ['projectId', 'project_id'],
        'storage_bucket' => ['storageBucket', 'storage_bucket'],
        'messaging_sender_id' => ['messagingSenderId', 'messaging_sender_id'],
        'app_id' => ['appId', 'app_id'],
    ];

    /**
     * Safely parse a Firebase Web App configuration from a string (JS snippet or JSON) or array.
     * Never executes JavaScript code (no eval, exec, or shell execution).
     *
     * @param  string|array<string, mixed>  $input
     * @return array<string, string|null>
     */
    public function parse(string|array $input): array
    {
        if (is_array($input)) {
            return $this->parseArray($input);
        }

        $inputString = trim((string) $input);
        if ($inputString === '') {
            return $this->emptyNormalized();
        }

        // 1. Try standard JSON decode first
        $jsonDecoded = json_decode($inputString, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
            return $this->parseArray($jsonDecoded);
        }

        // 2. Safely parse JavaScript code snippet using regex extraction
        return $this->parseJsSnippet($inputString);
    }

    /**
     * Parse and normalize an associative array of Firebase keys.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, string|null>
     */
    protected function parseArray(array $array): array
    {
        $normalized = $this->emptyNormalized();

        foreach (self::FIELD_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $array) && $array[$alias] !== null) {
                    $val = trim((string) $array[$alias]);
                    $normalized[$field] = $val !== '' ? $val : null;
                    break;
                }
            }
        }

        return $normalized;
    }

    /**
     * Safely extract Firebase config properties from raw JavaScript text snippet.
     *
     * @return array<string, string|null>
     */
    protected function parseJsSnippet(string $text): array
    {
        // Strip multi-line comments: /* ... */
        $clean = preg_replace('!/\*.*?\*/!s', '', $text) ?? $text;
        // Strip single-line comments: // ...
        $clean = preg_replace('!//.*$!m', '', $clean) ?? $clean;

        $normalized = $this->emptyNormalized();

        foreach (self::FIELD_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                // Matches key: "value", key: 'value', "key": "value", key: 123456
                $pattern = '/(?:["\']?' . preg_quote($alias, '/') . '["\']?)\s*:\s*(?:["\']([^"\'\r\n]*)["\']|([^\s,;}{]+))/i';
                if (preg_match($pattern, $clean, $matches)) {
                    $val = isset($matches[1]) && $matches[1] !== '' ? $matches[1] : ($matches[2] ?? '');
                    $val = trim(trim($val), '"\'');
                    if ($val !== '') {
                        $normalized[$field] = $val;
                        break;
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Return empty normalized structure.
     *
     * @return array<string, null>
     */
    protected function emptyNormalized(): array
    {
        return [
            'api_key' => null,
            'auth_domain' => null,
            'project_id' => null,
            'storage_bucket' => null,
            'messaging_sender_id' => null,
            'app_id' => null,
        ];
    }
}
