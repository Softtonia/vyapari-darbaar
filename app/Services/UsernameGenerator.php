<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class UsernameGenerator
{
    /**
     * Maximum retry attempts for username collision resolution.
     */
    protected const MAX_ATTEMPTS = 50;

    /**
     * Generate a unique username for a given name and email.
     *
     * @param  string  $name
     * @param  string  $email
     * @return string
     *
     * @throws RuntimeException
     */
    public function generate(string $name, string $email): string
    {
        $base = $this->generateBase($name, $email);
        $lockKey = 'vyapari_darbaar:user_username:'.$base;

        $lock = Cache::lock($lockKey, 10);

        try {
            // Block up to 5 seconds to acquire the lock
            $lock->block(5);

            for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
                $candidate = ($attempt === 1) ? $base : $base.$attempt;

                // Ensure candidate does not exceed the 60-character column limit
                if (strlen($candidate) > 60) {
                    $candidate = substr($candidate, 0, 60);
                }

                if (! User::query()->where('username', $candidate)->exists()) {
                    return $candidate;
                }
            }

            throw new RuntimeException('Unable to generate a unique username after '.self::MAX_ATTEMPTS.' attempts.');
        } finally {
            $lock->release();
        }
    }

    /**
     * Generate a normalized lowercase base username.
     *
     * @param  string  $name
     * @param  string  $email
     * @return string
     */
    protected function generateBase(string $name, string $email): string
    {
        // Replace spaces with dots, convert to lowercase
        $cleaned = Str::lower(trim($name));
        $cleaned = preg_replace('/\s+/', '.', $cleaned) ?? '';

        // Keep only alphanumeric, dots, and underscores
        $cleaned = preg_replace('/[^a-z0-9._]/', '', $cleaned) ?? '';

        // Strip consecutive dots and leading/trailing dots
        $cleaned = preg_replace('/\.+/', '.', $cleaned) ?? '';
        $cleaned = trim($cleaned, '._');

        // Fallback to email local part if name produced empty string
        if ($cleaned === '') {
            $localPart = Str::lower(strstr($email, '@', true) ?: 'user');
            $cleaned = preg_replace('/[^a-z0-9._]/', '', $localPart) ?? 'user';
            $cleaned = trim($cleaned, '._');
        }

        if ($cleaned === '') {
            $cleaned = 'user';
        }

        // Limit base to 50 chars to leave room for suffix numbers
        return substr($cleaned, 0, 50);
    }
}
