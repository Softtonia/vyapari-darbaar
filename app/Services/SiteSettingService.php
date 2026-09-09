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
        return Cache::remember(self::PUBLIC_CACHE_KEY, self::PUBLIC_CACHE_TTL, function () {
            return SiteSetting::query()->find(1) ?? SiteSetting::query()->first();
        });
    }

    /**
     * Retrieve admin site settings (direct from database).
     */
    public function getAdminSettings(): SiteSetting
    {
        return SiteSetting::query()->find(1) ?? SiteSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'site_name_en' => 'Vyapari Darbar',
                'site_name_hi' => 'व्यापारी दरबार',
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
        } catch (Throwable $e) {
            // Clean up any newly stored files in this request if an upload failed
            foreach ($newStoredFiles as $filePath) {
                $disk->delete($filePath);
            }
            throw $e;
        }

        $oldWebLogo = null;
        $oldMobileLogo = null;

        // 2. Database transaction with row lock
        try {
            $setting = DB::transaction(function () use ($data, $adminId, $newStoredFiles, &$oldWebLogo, &$oldMobileLogo) {
                /** @var SiteSetting|null $setting */
                $setting = SiteSetting::query()
                    ->whereKey(1)
                    ->lockForUpdate()
                    ->first();

                if (! $setting) {
                    $setting = SiteSetting::create([
                        'id' => 1,
                        'site_name_en' => 'Vyapari Darbar',
                        'site_name_hi' => 'व्यापारी दरबार',
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                    ]);
                }

                $oldWebLogo = $setting->web_logo;
                $oldMobileLogo = $setting->mobile_logo;

                $updatePayload = [];

                $textFields = [
                    'site_name_en',
                    'site_name_hi',
                    'site_title_en',
                    'site_title_hi',
                    'site_description_en',
                    'site_description_hi',
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

        return $setting;
    }
}
