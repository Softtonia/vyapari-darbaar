<?php

namespace App\Services\Firebase;

use App\Models\FirebaseSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FirebaseConfigService
{
    public const PUBLIC_CACHE_KEY = 'settings:firebase:public';
    public const PUBLIC_CACHE_TTL = 3600; // 1 hour

    /**
     * Retrieve the singleton Firebase setting record.
     */
    public function getSettings(): ?FirebaseSetting
    {
        return FirebaseSetting::query()->find(1);
    }

    /**
     * Retrieve the safe public Firebase configuration.
     * Cached in Redis. Returns null if missing or disabled.
     */
    public function getPublicConfig(): ?array
    {
        return Cache::remember(self::PUBLIC_CACHE_KEY, self::PUBLIC_CACHE_TTL, function () {
            $setting = $this->getSettings();

            if (! $setting || ! $setting->status) {
                return null;
            }

            return [
                'api_key' => $setting->api_key,
                'auth_domain' => $setting->auth_domain,
                'project_id' => $setting->project_id,
                'storage_bucket' => $setting->storage_bucket,
                'messaging_sender_id' => $setting->messaging_sender_id,
                'app_id' => $setting->app_id,
                'vapid_key' => $setting->vapid_key,
            ];
        });
    }

    /**
     * Retrieve decrypted service account credentials at runtime.
     * Never cached in Redis or serialized.
     */
    public function getDecryptedServiceAccount(): ?array
    {
        $setting = $this->getSettings();
        if (! $setting || empty($setting->service_account_json)) {
            return null;
        }

        $decoded = json_decode((string) $setting->service_account_json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Update or create singleton Firebase settings in a database transaction.
     * Retains existing service account credentials when omitted, empty, or masked.
     * Invalidates Redis public config cache only after successful commit.
     */
    public function updateSettings(array $data): FirebaseSetting
    {
        $setting = DB::transaction(function () use ($data) {
            $existing = FirebaseSetting::query()->lockForUpdate()->find(1);

            $projectId = (string) $data['project_id'];
            $attributes = [
                'api_key' => (string) $data['api_key'],
                'auth_domain' => (string) ($data['auth_domain'] ?? ($projectId.'.firebaseapp.com')),
                'project_id' => $projectId,
                'storage_bucket' => $data['storage_bucket'] ?? null,
                'messaging_sender_id' => (string) $data['messaging_sender_id'],
                'app_id' => (string) $data['app_id'],
                'vapid_key' => (string) $data['vapid_key'],
                'status' => (bool) $data['status'],
            ];

            $serviceAccountRaw = $data['service_account_json'] ?? null;
            $shouldRetain = $serviceAccountRaw === null
                || trim((string) $serviceAccountRaw) === ''
                || trim((string) $serviceAccountRaw) === '********';

            if ($shouldRetain) {
                if ($existing && ! empty($existing->service_account_json)) {
                    $attributes['service_account_json'] = $existing->service_account_json;
                }
            } else {
                $attributes['service_account_json'] = (string) $serviceAccountRaw;
            }

            return FirebaseSetting::updateOrCreate(
                ['id' => 1],
                $attributes
            );
        });

        // Invalidate public Redis cache only after successful DB commit
        $this->invalidatePublicCache();

        return $setting;
    }

    /**
     * Invalidate the public Firebase config cache.
     */
    public function invalidatePublicCache(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }
}
