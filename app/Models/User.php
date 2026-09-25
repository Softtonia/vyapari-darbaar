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
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->attributes['username']) && ! empty($user->attributes['email'])) {
                $user->attributes['username'] = explode('@', $user->attributes['email'])[0].'_'.substr(md5(uniqid()), 0, 4);
            }
            if (empty($user->attributes['name'])) {
                $name = '';
                if (empty($name) && ! empty($user->attributes['username'])) {
                    $name = $user->attributes['username'];
                }
                if (empty($name) && ! empty($user->attributes['email'])) {
                    $name = explode('@', $user->attributes['email'])[0];
                }
                $user->attributes['name'] = ! empty($name) ? $name : 'User';
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'full_name',
        'name',
        'phone_number',
        'username',
        'email',
        'password',
        'status',
        'suspension_reason',
        'must_change_password',
        'is_default',
        'last_login_at',
        'created_by',
        'created_by_user_id',
        'created_by_admin_id',
        'email_verified_at',
        'date_of_birth',
        'gender',
        'alternate_number',
        'profile_photo',
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
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_default' => 'boolean',
        ];
    }


    /**
     * Helper to check if user has super admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Helper to check if user has administrative access.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'editor']);
    }

    /**
     * Backward-compatible created_by_user_id getter.
     */
    public function getCreatedByUserIdAttribute(): ?int
    {
        return $this->attributes['created_by'] ?? null;
    }

    /**
     * Backward-compatible created_by_user_id setter.
     */
    public function setCreatedByUserIdAttribute(?int $value): void
    {
        $this->attributes['created_by'] = $value;
        unset($this->attributes['created_by_user_id']);
    }

    /**
     * Backward-compatible created_by_admin_id getter.
     */
    public function getCreatedByAdminIdAttribute(): ?int
    {
        return $this->attributes['created_by'] ?? null;
    }

    /**
     * Backward-compatible created_by_admin_id setter.
     */
    public function setCreatedByAdminIdAttribute(?int $value): void
    {
        $this->attributes['created_by'] = $value;
        unset($this->attributes['created_by_admin_id']);
    }

    /**
     * Get the administrator/creator who created this user account.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /**
     * Direct relationship to user_has_roles table.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Role, $this>
     */
    public function userRoles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_has_roles', 'user_id', 'role_id')
            ->withTimestamps();
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

    /**
     * In-app notifications for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\InAppNotification, $this>
     */
    public function inAppNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InAppNotification::class);
    }

    /**
     * Unread in-app notifications for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\InAppNotification, $this>
     */
    public function unreadInAppNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InAppNotification::class)->whereNull('read_at');
    }

    /**
     * Topics subscribed by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\NotificationTopic, $this>
     */
    public function notificationTopics(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(NotificationTopic::class, 'notification_topic_users', 'user_id', 'notification_topic_id')
            ->withPivot('created_at');
    }

    /**
     * Notification delivery logs for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\NotificationLog, $this>
     */
    public function notificationLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function businessDocuments()
    {
        return $this->hasMany(BusinessDocument::class);
    }

    public function locations()
    {
        return $this->hasMany(UserLocation::class);
    }
}