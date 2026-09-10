<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

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
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'phone_number',
        'username',
        'email',
        'password',
        'status',
        'must_change_password',
        'created_by_admin_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'full_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        $fullName = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $fullName !== '' ? $fullName : ($this->attributes['name'] ?? '');
    }

    /**
     * Get or fallback name attribute.
     */
    public function getNameAttribute(?string $value): string
    {
        if (!empty($value)) {
            return $value;
        }

        return $this->full_name;
    }

    /**
     * Get the administrator who created this user account.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    /**
     * Companies associated with this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Company, $this>
     */
    public function companies(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_has_companies')
            ->withPivot('role', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Get the primary company associated with the user.
     *
     * @return \App\Models\Company|null
     */
    public function getCompanyAttribute(): ?Company
    {
        return $this->companies()->wherePivot('is_primary', true)->first()
            ?? $this->companies()->first();
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\UserResetPasswordNotification($token));
    }

    /**
     * Get all notification devices registered for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\NotificationDevice, $this>
     */
    public function notificationDevices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationDevice::class);
    }

    /**
     * Get active notification devices registered for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\NotificationDevice, $this>
     */
    public function activeNotificationDevices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationDevice::class)->where('is_active', true);
    }
}

