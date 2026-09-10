<?php

namespace App\Services;

use InvalidArgumentException;

class FirebaseWebConfigParserService
{
    /**
     * Parse Firebase Web App Configuration snippet or JSON.
     * Extracts only approved properties without evaluating JavaScript code.
     *
     * @param  string|array<string, mixed>  $rawConfig
     * @return array{apiKey: string, authDomain: string, projectId: string, storageBucket: ?string, messagingSenderId: string, appId: string}
     *
     * @throws InvalidArgumentException
     */
    public function parse(string|array $rawConfig): array
    {
        if (is_array($rawConfig)) {
            return $this->extractAndValidate($rawConfig);
        }

        $trimmed = trim($rawConfig);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Firebase web configuration is empty.');
        }

        // Try direct JSON decode first
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $this->extractAndValidate($decoded);
        }

        // Parse JavaScript object literal safely with regex
        $extracted = $this->parseJsObjectLiteral($trimmed);
        if (! empty($extracted)) {
            return $this->extractAndValidate($extracted);
        }

        throw new InvalidArgumentException('Invalid Firebase web configuration format.');
    }

    /**
     * Cross-check web config project ID against service account project ID.
     *
     * @param  string  $webProjectId
     * @param  array<string, mixed>|string|null  $serviceAccount
     * @return bool
     */
    public function validateProjectMatch(string $webProjectId, array|string|null $serviceAccount): bool
    {
        if (empty($serviceAccount)) {
            return true;
        }

        if (is_string($serviceAccount)) {
            $serviceAccount = json_decode($serviceAccount, true);
        }

        if (! is_array($serviceAccount) || empty($serviceAccount['project_id'])) {
            return true;
        }

        return trim($webProjectId) === trim((string) $serviceAccount['project_id']);
    }

    /**
     * Parse JS object literal using regex pattern matching on key-value pairs.
     *
     * @param  string  $jsContent
     * @return array<string, string>
     */
    protected function parseJsObjectLiteral(string $jsContent): array
    {
        $result = [];

        // Match keys like apiKey: "..." or "apiKey": '...' or apiKey: `...`
        $pattern = '/(?:["\']?([a-zA-Z0-9_]+)["\']?\s*:\s*(?:["\'`](.*?)["\'`]|([a-zA-Z0-9_.-]+)))/m';

        if (preg_match_all($pattern, $jsContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = trim($match[1]);
                $val = isset($match[2]) && $match[2] !== '' ? $match[2] : ($match[3] ?? '');
                $result[$key] = trim($val);
            }
        }

        return $result;
    }

    /**
     * Extract only approved properties and validate presence of FCM-essential fields.
     *
     * @param  array<string, mixed>  $data
     * @return array{apiKey: string, authDomain: string, projectId: string, storageBucket: ?string, messagingSenderId: string, appId: string}
     */
    protected function extractAndValidate(array $data): array
    {
        $apiKey = trim((string) ($data['apiKey'] ?? $data['api_key'] ?? ''));
        $projectId = trim((string) ($data['projectId'] ?? $data['project_id'] ?? ''));
        $messagingSenderId = trim((string) ($data['messagingSenderId'] ?? $data['messaging_sender_id'] ?? ''));
        $appId = trim((string) ($data['appId'] ?? $data['app_id'] ?? ''));

        if ($apiKey === '' || $projectId === '' || $messagingSenderId === '' || $appId === '') {
            throw new InvalidArgumentException('Missing required Firebase configuration parameters (apiKey, projectId, messagingSenderId, appId).');
        }

        // authDomain and storageBucket are optional for FCM; auto-derive defaults if omitted
        $authDomain = trim((string) ($data['authDomain'] ?? $data['auth_domain'] ?? ''));
        if ($authDomain === '') {
            $authDomain = $projectId.'.firebaseapp.com';
        }

        $storageBucket = isset($data['storageBucket']) ? trim((string) $data['storageBucket']) : (isset($data['storage_bucket']) ? trim((string) $data['storage_bucket']) : null);
        if ($storageBucket === '') {
            $storageBucket = null;
        }

        return [
            'apiKey' => $apiKey,
            'authDomain' => $authDomain,
            'projectId' => $projectId,
            'storageBucket' => $storageBucket,
            'messagingSenderId' => $messagingSenderId,
            'appId' => $appId,
        ];
    }
}
