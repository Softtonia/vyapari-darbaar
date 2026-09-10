<?php

namespace App\Services\Firebase;

use App\Models\NotificationDevice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * @param  string|null  $ipAddress
     * @return NotificationDevice
     */
    public function registerDevice(User $user, array $data, ?string $ipAddress = null): NotificationDevice
    {
        $plainToken = (string) $data['fcm_token'];
        $tokenHash = hash('sha256', $plainToken);
        $ip = $ipAddress ?? ($data['ip_address'] ?? null);

        return DB::transaction(function () use ($user, $data, $plainToken, $tokenHash, $ip) {
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
                    'ip_address' => $ip,
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
                    'ip_address' => $ip,
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
                    'ip_address' => $ip,
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

    /**
     * Paginate notification devices for Admin.
     * Note: Search searches user name/email, device_name, browser, ip_address - NEVER raw token.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator
     */
    public function paginateForAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = NotificationDevice::query()
            ->with('user:id,first_name,last_name,name,email,username');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['device_type'])) {
            $query->where('device_type', (string) $filters['device_type']);
        }

        if (! empty($filters['browser'])) {
            $query->where('browser', (string) $filters['browser']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('device_name', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'created_at');
        $allowedSorts = ['id', 'device_type', 'device_name', 'browser', 'is_active', 'last_used_at', 'created_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Find device for Admin.
     *
     * @param  int  $id
     * @return NotificationDevice|null
     */
    public function findForAdmin(int $id): ?NotificationDevice
    {
        return NotificationDevice::query()
            ->with('user:id,first_name,last_name,name,email,username')
            ->find($id);
    }

    /**
     * Update device status (active / inactive).
     *
     * @param  NotificationDevice  $device
     * @param  bool  $isActive
     * @return NotificationDevice
     */
    public function updateDeviceStatus(NotificationDevice $device, bool $isActive): NotificationDevice
    {
        $device->update(['is_active' => $isActive]);

        return $device->fresh();
    }

    /**
     * Delete a single device.
     *
     * @param  NotificationDevice  $device
     * @return bool
     */
    public function deleteDevice(NotificationDevice $device): bool
    {
        return (bool) $device->delete();
    }

    /**
     * Bulk delete notification devices.
     *
     * @param  array<int, int>  $ids
     * @return int Count of deleted devices
     */
    public function bulkDeleteDevices(array $ids): int
    {
        return NotificationDevice::whereIn('id', $ids)->delete();
    }

    /**
     * Generate safely masked token string for display in Admin UI.
     *
     * @param  string|null  $plainToken
     * @return string
     */
    public static function maskToken(?string $plainToken): string
    {
        if (empty($plainToken)) {
            return '********';
        }

        $len = strlen($plainToken);
        if ($len <= 11) {
            return substr($plainToken, 0, 3) . '*****' . substr($plainToken, -2);
        }

        return substr($plainToken, 0, 7) . '*************' . substr($plainToken, -4);
    }
}
