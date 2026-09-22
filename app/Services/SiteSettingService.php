<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SiteSettingService
{
    public const PUBLIC_CACHE_KEY = 'site_settings:public';
    public const PUBLIC_CACHE_TTL = 3600; // 1 hour
    public const LOGO_STORAGE_DIR = 'site-settings/logos';
    public const STORAGE_DISK = 'public';

    /**
     * Retrieve public site settings (cached).
     */
    public function getPublicSettings(): ?SiteSetting
    {
        try {
            $setting = Cache::get(self::PUBLIC_CACHE_KEY);

            if ($setting instanceof SiteSetting) {
                return $setting;
            }

            if ($setting !== null) {
                Cache::forget(self::PUBLIC_CACHE_KEY);
            }
        } catch (Throwable) {
            Cache::forget(self::PUBLIC_CACHE_KEY);
        }

        $setting = SiteSetting::query()->find(1) ?? SiteSetting::query()->first();

        if ($setting) {
            Cache::put(self::PUBLIC_CACHE_KEY, $setting, self::PUBLIC_CACHE_TTL);
        }

        return $setting;
    }

    /**
     * Retrieve admin site settings (direct from database).
     */
    public function getAdminSettings(): SiteSetting
    {
        return SiteSetting::query()->find(1) ?? SiteSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'site_name' => 'Vyapari Darbar',
                'admin_email' => 'admin@vyaparidarbaar.com',
                'timezone' => 'Asia/Kolkata',
                'default_language' => 'en',
                'currency' => 'INR',
            ]
        );
    }

    /**
     * Update site settings singleton.
     *
     * @param  array<string, mixed>  $data
     * @param  int|null  $adminId
     * @return SiteSetting
     *
     * @throws Throwable
     */
    public function updateSettings(array $data, ?int $adminId = null): SiteSetting
    {
        $newStoredFiles = [];
        $disk = Storage::disk(self::STORAGE_DISK);

        // 1. Guarded file storage
        try {
            if (isset($data['web_logo']) && $data['web_logo'] instanceof UploadedFile) {
                $path = $data['web_logo']->store(self::LOGO_STORAGE_DIR, self::STORAGE_DISK);
                if ($path === false) {
                    throw new \RuntimeException('Failed to store web logo.');
                }
                $newStoredFiles['web_logo'] = $path;
            }

            if (isset($data['mobile_logo']) && $data['mobile_logo'] instanceof UploadedFile) {
                $path = $data['mobile_logo']->store(self::LOGO_STORAGE_DIR, self::STORAGE_DISK);
                if ($path === false) {
                    throw new \RuntimeException('Failed to store mobile logo.');
                }
                $newStoredFiles['mobile_logo'] = $path;
            }

            if (isset($data['favicon']) && $data['favicon'] instanceof UploadedFile) {
                $path = $data['favicon']->store(self::LOGO_STORAGE_DIR, self::STORAGE_DISK);
                if ($path === false) {
                    throw new \RuntimeException('Failed to store favicon.');
                }
                $newStoredFiles['favicon'] = $path;
            }
        } catch (Throwable $e) {
            // Clean up any newly stored files in this request if an upload failed
            foreach ($newStoredFiles as $filePath) {
                $disk->delete($filePath);
            }
            throw $e;
        }

        $oldWebLogo = null;
        $oldMobileLogo = null;
        $oldFavicon = null;

        // 2. Database transaction with row lock
        try {
            $setting = DB::transaction(function () use ($data, $adminId, $newStoredFiles, &$oldWebLogo, &$oldMobileLogo, &$oldFavicon) {
                /** @var SiteSetting|null $setting */
                $setting = SiteSetting::query()
                    ->whereKey(1)
                    ->lockForUpdate()
                    ->first();

                if (! $setting) {
                    $setting = SiteSetting::create([
                        'id' => 1,
                        'site_name' => 'Vyapari Darbar',
                        'admin_email' => 'admin@vyaparidarbaar.com',
                        'timezone' => 'Asia/Kolkata',
                        'default_language' => 'en',
                        'currency' => 'INR',
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                    ]);
                }

                $oldWebLogo = $setting->web_logo;
                $oldMobileLogo = $setting->mobile_logo;
                $oldFavicon = $setting->favicon;

                $updatePayload = [];

                $textFields = [
                    'site_name',
                    'site_title',
                    'site_description',
                    'admin_email',
                    'timezone',
                    'default_language',
                    'currency',
                ];

                foreach ($textFields as $field) {
                    if (array_key_exists($field, $data)) {
                        $updatePayload[$field] = $data[$field];
                    }
                }

                if (isset($newStoredFiles['web_logo'])) {
                    $updatePayload['web_logo'] = $newStoredFiles['web_logo'];
                }

                if (isset($newStoredFiles['mobile_logo'])) {
                    $updatePayload['mobile_logo'] = $newStoredFiles['mobile_logo'];
                }

                if (isset($newStoredFiles['favicon'])) {
                    $updatePayload['favicon'] = $newStoredFiles['favicon'];
                }

                $updatePayload['updated_by'] = $adminId;

                $setting->update($updatePayload);

                return $setting->fresh();
            });
        } catch (Throwable $dbException) {
            // If database transaction fails, clean up all newly stored files
            foreach ($newStoredFiles as $filePath) {
                $disk->delete($filePath);
            }
            throw $dbException;
        }

        // 3. Post-commit: Invalidate public cache
        Cache::forget(self::PUBLIC_CACHE_KEY);

        // 4. Post-commit: Superseded old file cleanup (fail-safe)
        if (isset($newStoredFiles['web_logo']) && ! empty($oldWebLogo) && $oldWebLogo !== $newStoredFiles['web_logo']) {
            try {
                if ($disk->exists($oldWebLogo)) {
                    $disk->delete($oldWebLogo);
                }
            } catch (Throwable $cleanupException) {
                Log::warning('Failed to delete superseded old web logo: ' . $oldWebLogo, [
                    'error' => $cleanupException->getMessage(),
                ]);
            }
        }

        if (isset($newStoredFiles['mobile_logo']) && ! empty($oldMobileLogo) && $oldMobileLogo !== $newStoredFiles['mobile_logo']) {
            try {
                if ($disk->exists($oldMobileLogo)) {
                    $disk->delete($oldMobileLogo);
                }
            } catch (Throwable $cleanupException) {
                Log::warning('Failed to delete superseded old mobile logo: ' . $oldMobileLogo, [
                    'error' => $cleanupException->getMessage(),
                ]);
            }
        }

        if (isset($newStoredFiles['favicon']) && ! empty($oldFavicon) && $oldFavicon !== $newStoredFiles['favicon']) {
            try {
                if ($disk->exists($oldFavicon)) {
                    $disk->delete($oldFavicon);
                }
            } catch (Throwable $cleanupException) {
                Log::warning('Failed to delete superseded old favicon: ' . $oldFavicon, [
                    'error' => $cleanupException->getMessage(),
                ]);
            }
        }

        return $setting;
    }
}
