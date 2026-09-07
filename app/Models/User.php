<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
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
     * Get the administrator who created this user account.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    /**
     * Get the roles assigned to this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Role, $this>
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * Determine if the user has a specific role by slug or model.
     */
    public function hasRole(string|Role $role): bool
    {
        $slug = $role instanceof Role ? $role->slug : $role;

        return $this->roles->contains('slug', $slug);
    }

    /**
     * Assign a role to the user.
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
     * Sync roles for the user.
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
}
