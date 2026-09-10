<?php

namespace App\Services\Firebase\Contracts;

use App\Services\Firebase\Data\FirebaseAccessToken;

interface FirebaseAccessTokenProvider
{
    /**
     * Generate or retrieve a valid OAuth2 access token from the given service account.
     *
     * @param  array<string, mixed>  $serviceAccount
     * @param  string  $projectId
     * @return FirebaseAccessToken
     */
    public function getAccessToken(array $serviceAccount, string $projectId): FirebaseAccessToken;
}
