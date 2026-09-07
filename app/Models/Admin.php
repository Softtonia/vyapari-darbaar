<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admins';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get all users created by this administrator.
     *
     * @return HasMany<User, $this>
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by_admin_id');
    }

    /**
     * Get the roles assigned to this administrator.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Role, $this>
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_role')->withTimestamps();
    }

    /**
     * Determine if the admin has a specific role by slug or model.
     */
    public function hasRole(string|Role $role): bool
    {
        $slug = $role instanceof Role ? $role->slug : $role;

        return $this->roles->contains('slug', $slug);
    }

    /**
     * Assign a role to the administrator.
     */
    public function assignRole(string|Role|int $role): void
    {
        $roleId = match (true) {
            $role instanceof Role => $role->id,
            is_numeric($role) => (int) $role,
            default => Role::query()->where('slug', $role)->value('id'),
        };

        if ($roleId) {
            $this->roles()->syncWithoutDetaching([$roleId]);
        }
    }

    /**
     * Sync roles for the administrator.
     *
     * @param  array<int|string|Role>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $roleIds = array_filter(array_map(function ($role) {
            return match (true) {
                $role instanceof Role => $role->id,
                is_numeric($role) => (int) $role,
                default => Role::query()->where('slug', $role)->value('id'),
            };
        }, $roles));

        $this->roles()->sync($roleIds);
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
