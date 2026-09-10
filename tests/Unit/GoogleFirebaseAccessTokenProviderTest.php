<?php

namespace Tests\Unit;

use App\Services\Firebase\Data\FirebaseAccessToken;
use App\Services\Firebase\GoogleFirebaseAccessTokenProvider;
use PHPUnit\Framework\TestCase;

class GoogleFirebaseAccessTokenProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        GoogleFirebaseAccessTokenProvider::clearCache();
    }

    protected function tearDown(): void
    {
        GoogleFirebaseAccessTokenProvider::clearCache();
        parent::tearDown();
    }

    /**
     * Helper to create a test provider with a controllable mock token generator.
     */
    protected function createMockableProvider(callable $generator): GoogleFirebaseAccessTokenProvider
    {
        return new class($generator) extends GoogleFirebaseAccessTokenProvider {
            public int $fetchCallCount = 0;
            protected $generator;

            public function __construct(callable $generator)
            {
                $this->generator = $generator;
            }

            protected function fetchTokenFromGoogle(array $serviceAccount, string $fingerprint): FirebaseAccessToken
            {
                $this->fetchCallCount++;
                return ($this->generator)($serviceAccount, $fingerprint, $this->fetchCallCount);
            }
        };
    }

    public function test_same_credentials_with_valid_cached_token_reuses_token_without_second_fetch(): void
    {
        $provider = $this->createMockableProvider(function ($sa, $fp, $count) {
            return new FirebaseAccessToken("token_{$count}", time() + 3600, $fp);
        });

        $serviceAccount = [
            'client_email' => 'service-account@vyapari-darbaar-test.iam.gserviceaccount.com',
            'private_key_id' => 'key_123',
        ];

        $token1 = $provider->getAccessToken($serviceAccount, 'project-a');
        $this->assertEquals('token_1', $token1->token);
        $this->assertEquals(1, $provider->fetchCallCount);

        // Second call with same credentials
        $token2 = $provider->getAccessToken($serviceAccount, 'project-a');
        $this->assertEquals('token_1', $token2->token);
        $this->assertEquals(1, $provider->fetchCallCount); // fetch was NOT called again
    }

    public function test_credential_change_forces_new_token_generation(): void
    {
        $provider = $this->createMockableProvider(function ($sa, $fp, $count) {
            return new FirebaseAccessToken("token_{$count}", time() + 3600, $fp);
        });

        $serviceAccount1 = [
            'client_email' => 'sa1@vyapari-darbaar-test.iam.gserviceaccount.com',
            'private_key_id' => 'key_1',
        ];
        $serviceAccount2 = [
            'client_email' => 'sa2@vyapari-darbaar-test.iam.gserviceaccount.com',
            'private_key_id' => 'key_2',
        ];

        $token1 = $provider->getAccessToken($serviceAccount1, 'project-a');
        $this->assertEquals('token_1', $token1->token);
        $this->assertEquals(1, $provider->fetchCallCount);

        // Call with different credentials
        $token2 = $provider->getAccessToken($serviceAccount2, 'project-a');
        $this->assertEquals('token_2', $token2->token);
        $this->assertEquals(2, $provider->fetchCallCount);
    }

    public function test_project_id_change_forces_new_token_generation(): void
    {
        $provider = $this->createMockableProvider(function ($sa, $fp, $count) {
            return new FirebaseAccessToken("token_{$count}", time() + 3600, $fp);
        });

        $serviceAccount = [
            'client_email' => 'sa@vyapari-darbaar-test.iam.gserviceaccount.com',
            'private_key_id' => 'key_1',
        ];

        $token1 = $provider->getAccessToken($serviceAccount, 'project-a');
        $this->assertEquals('token_1', $token1->token);
        $this->assertEquals(1, $provider->fetchCallCount);

        // Call with different project ID
        $token2 = $provider->getAccessToken($serviceAccount, 'project-b');
        $this->assertEquals('token_2', $token2->token);
        $this->assertEquals(2, $provider->fetchCallCount);
    }

    public function test_token_nearing_expiry_safety_window_is_refreshed(): void
    {
        $provider = $this->createMockableProvider(function ($sa, $fp, $count) {
            // First token expires in 30 seconds (within the 60-second safety window)
            // Second token expires in 1 hour
            $expiresAt = ($count === 1) ? time() + 30 : time() + 3600;
            return new FirebaseAccessToken("token_{$count}", $expiresAt, $fp);
        });

        $serviceAccount = [
            'client_email' => 'sa@vyapari-darbaar-test.iam.gserviceaccount.com',
            'private_key_id' => 'key_1',
        ];

        // 1st request generates token expiring in 30s
        $token1 = $provider->getAccessToken($serviceAccount, 'project-a');
        $this->assertEquals('token_1', $token1->token);
        $this->assertEquals(1, $provider->fetchCallCount);

        // 2nd request detects that token is within safety window (30s < 60s) and generates fresh token
        $token2 = $provider->getAccessToken($serviceAccount, 'project-a');
        $this->assertEquals('token_2', $token2->token);
        $this->assertEquals(2, $provider->fetchCallCount);
    }

    public function test_fingerprint_handles_missing_private_key_id_using_private_key_hash(): void
    {
        $provider = new GoogleFirebaseAccessTokenProvider;

        $saWithoutKeyId1 = [
            'client_email' => 'sa@example.com',
            'private_key' => 'fake_private_key_content_1',
        ];
        $saWithoutKeyId2 = [
            'client_email' => 'sa@example.com',
            'private_key' => 'fake_private_key_content_2',
        ];

        $fp1 = $provider->calculateFingerprint($saWithoutKeyId1, 'project-a');
        $fp2 = $provider->calculateFingerprint($saWithoutKeyId2, 'project-a');

        $this->assertNotEmpty($fp1);
        $this->assertNotEmpty($fp2);
        $this->assertNotEquals($fp1, $fp2);
    }
}
