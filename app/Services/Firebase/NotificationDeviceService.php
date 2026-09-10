<?php

namespace App\Services\Firebase;

use App\Models\NotificationDevice;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class NotificationDeviceService
{
    /**
     * Register or update a user's notification device.
     * Handles concurrent registrations and reassignment across user accounts safely.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $data
     * @return NotificationDevice
     */
    public function registerDevice(User $user, array $data): NotificationDevice
    {
        $plainToken = (string) $data['fcm_token'];
        $tokenHash = hash('sha256', $plainToken);

        return DB::transaction(function () use ($user, $data, $plainToken, $tokenHash) {
            $device = NotificationDevice::query()
                ->where('fcm_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($device) {
                $device->fill([
                    'user_id' => $user->id,
                    'fcm_token' => $plainToken,
                    'device_type' => $data['device_type'],
                    'device_name' => $data['device_name'] ?? null,
                    'browser' => $data['browser'] ?? null,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
                $device->save();

                return $device;
            }

            try {
                return NotificationDevice::create([
                    'user_id' => $user->id,
                    'fcm_token' => $plainToken,
                    'fcm_token_hash' => $tokenHash,
                    'device_type' => $data['device_type'],
                    'device_name' => $data['device_name'] ?? null,
                    'browser' => $data['browser'] ?? null,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
            } catch (QueryException $e) {
                // In case of a concurrent insert race condition caught by UNIQUE(fcm_token_hash)
                $device = NotificationDevice::query()
                    ->where('fcm_token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->firstOrFail();

                $device->fill([
                    'user_id' => $user->id,
                    'fcm_token' => $plainToken,
                    'device_type' => $data['device_type'],
                    'device_name' => $data['device_name'] ?? null,
                    'browser' => $data['browser'] ?? null,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
                $device->save();

                return $device;
            }
        });
    }

    /**
     * Deactivate a notification device belonging to the authenticated user.
     *
     * @param  User  $user
     * @param  string  $fcmToken
     * @return bool
     */
    public function deactivateUserDevice(User $user, string $fcmToken): bool
    {
        $tokenHash = hash('sha256', $fcmToken);

        $device = NotificationDevice::query()
            ->where('user_id', $user->id)
            ->where('fcm_token_hash', $tokenHash)
            ->first();

        if (! $device) {
            return false;
        }

        $device->update(['is_active' => false]);

        return true;
    }

    /**
     * Deactivate a device token by its SHA-256 hash when permanently unregistered.
     *
     * @param  string  $tokenHash
     * @return void
     */
    public function deactivateTokenHash(string $tokenHash): void
    {
        NotificationDevice::query()
            ->where('fcm_token_hash', $tokenHash)
            ->update(['is_active' => false]);
    }
}
