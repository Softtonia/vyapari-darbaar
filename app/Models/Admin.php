<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proxy / subclass of User for administrative contexts.
 * All accounts now live unified in the 'users' table.
 */
class Admin extends User
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The guard associated with this model for Spatie permissions.
     *
     * @var string
     */
    protected string $guard_name = 'web';

    /**
     * Get the class name for polymorphic relations.
     */
    public function getMorphClass(): string
    {
        return User::class;
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        parent::booted();
    }

    /**
     * Get all users created by this administrator.
     *
     * @return HasMany<User, $this>
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\AdminResetPasswordNotification($token));
    }
}
