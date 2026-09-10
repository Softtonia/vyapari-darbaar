<?php

namespace App\Services\Firebase\Data;

class FirebaseAccessToken
{
    public function __construct(
        public readonly string $token,
        public readonly int $expiresAt,
        public readonly string $credentialFingerprint
    ) {}

    /**
     * Check if the access token has expired or is nearing expiration within a safety window.
     */
    public function isExpired(int $safetyWindowSeconds = 60): bool
    {
        return time() >= ($this->expiresAt - $safetyWindowSeconds);
    }
}
