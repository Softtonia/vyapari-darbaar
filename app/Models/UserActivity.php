<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_activities';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'module',
        'action',
        'event',
        'description',
        'status',
        'ip_address',
        'user_agent',
        'properties',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that performed this activity.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope query by module.
     *
     * @param  Builder<UserActivity>  $query
     * @param  string|null  $module
     * @return Builder<UserActivity>
     */
    public function scopeModule(Builder $query, ?string $module): Builder
    {
        if (! empty($module)) {
            $query->where('module', $module);
        }

        return $query;
    }

    /**
     * Scope query by action.
     *
     * @param  Builder<UserActivity>  $query
     * @param  string|null  $action
     * @return Builder<UserActivity>
     */
    public function scopeAction(Builder $query, ?string $action): Builder
    {
        if (! empty($action)) {
            $query->where('action', $action);
        }

        return $query;
    }

    /**
     * Scope query by status.
     *
     * @param  Builder<UserActivity>  $query
     * @param  string|null  $status
     * @return Builder<UserActivity>
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! empty($status)) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * Scope query for keyword search.
     *
     * @param  Builder<UserActivity>  $query
     * @param  string|null  $search
     * @return Builder<UserActivity>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $term = trim((string) $search);
        if ($term !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $query->where(function (Builder $q) use ($escaped) {
                $q->where('description', 'like', "%{$escaped}%")
                    ->orWhere('module', 'like', "%{$escaped}%")
                    ->orWhere('action', 'like', "%{$escaped}%")
                    ->orWhere('event', 'like', "%{$escaped}%")
                    ->orWhere('ip_address', 'like', "%{$escaped}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($escaped) {
                        $userQuery->where('full_name', 'like', "%{$escaped}%")
                            ->orWhere('username', 'like', "%{$escaped}%")
                            ->orWhere('email', 'like', "%{$escaped}%");
                    });
            });
        }

        return $query;
    }
}
