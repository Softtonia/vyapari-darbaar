<?php

namespace App\Services\Firebase;

use App\Services\Firebase\Contracts\FirebaseAccessTokenProvider;
use App\Services\Firebase\Data\FirebaseAccessToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use RuntimeException;

class GoogleFirebaseAccessTokenProvider implements FirebaseAccessTokenProvider
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    public const SAFETY_WINDOW_SECONDS = 60;

    /**
     * In-memory process-local cache for active access tokens keyed by credential fingerprint.
     *
     * @var array<string, FirebaseAccessToken>
     */
    protected static array $tokenCache = [];

    /**
     * Generate or retrieve a valid OAuth2 access token from the given service account.
     *
     * @param  array<string, mixed>  $serviceAccount
     * @param  string  $projectId
     * @return FirebaseAccessToken
     */
    public function getAccessToken(array $serviceAccount, string $projectId): FirebaseAccessToken
    {
        $fingerprint = $this->calculateFingerprint($serviceAccount, $projectId);

        if (isset(self::$tokenCache[$fingerprint])) {
            $cached = self::$tokenCache[$fingerprint];
            if (! $cached->isExpired(self::SAFETY_WINDOW_SECONDS)) {
                return $cached;
            }
        }

        $token = $this->fetchTokenFromGoogle($serviceAccount, $fingerprint);
        self::$tokenCache[$fingerprint] = $token;

        return $token;
    }

    /**
     * Fetch a new OAuth2 access token using Google Auth credentials.
     *
     * @param  array<string, mixed>  $serviceAccount
     * @param  string  $fingerprint
     * @return FirebaseAccessToken
     */
    protected function fetchTokenFromGoogle(array $serviceAccount, string $fingerprint): FirebaseAccessToken
    {
        try {
            $credentials = new ServiceAccountCredentials(
                self::SCOPE,
                $serviceAccount
            );

            $authResult = $credentials->fetchAuthToken();
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to generate Google OAuth2 token: ' . $e->getMessage(), 0, $e);
        }

        if (empty($authResult['access_token'])) {
            $errorMsg = $authResult['error_description'] ?? $authResult['error'] ?? 'Unknown error';
            throw new RuntimeException('Google OAuth2 error: ' . $errorMsg);
        }

        $expiresAt = isset($authResult['expires_at'])
            ? (int) $authResult['expires_at']
            : (time() + (int) ($authResult['expires_in'] ?? 3600));

        return new FirebaseAccessToken(
            (string) $authResult['access_token'],
            $expiresAt,
            $fingerprint
        );
    }

    /**
     * Calculate a safe credential fingerprint.
     *
     * @param  array<string, mixed>  $serviceAccount
     * @param  string  $projectId
     * @return string
     */
    public function calculateFingerprint(array $serviceAccount, string $projectId): string
    {
        $clientEmail = (string) ($serviceAccount['client_email'] ?? '');
        $privateKeyIdentifier = ! empty($serviceAccount['private_key_id'])
            ? (string) $serviceAccount['private_key_id']
            : hash('sha256', (string) ($serviceAccount['private_key'] ?? ''));

        return hash('sha256', $projectId . '|' . $clientEmail . '|' . $privateKeyIdentifier);
    }

    /**
     * Clear all cached access tokens from memory. Useful for testing.
     */
    public static function clearCache(): void
    {
        self::$tokenCache = [];
    }
}
