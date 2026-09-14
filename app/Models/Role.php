<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $slug
 * @property bool $status
 * @property bool $is_default
 * @property bool $is_system
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static Builder|Role query()
 * @method static Builder|Role search(?string $search)
 * @method static Builder|Role status(mixed $status)
 * @method static Builder|Role system(mixed $isSystem)
 * @method static Builder|Role isDefault(mixed $isDefault)
 * @method static Builder|Role active()
 * @method static Builder|Role sort(string $sortBy = 'id', string $sortOrder = 'desc')
 */
class Role extends SpatieRole
{
    use HasFactory, SoftDeletes;

    /**
     * Allowed columns for sorting role queries safely.
     *
     * @var list<string>
     */
    public const ALLOWED_SORT_COLUMNS = [
        'id',
        'name',
        'guard_name',
        'slug',
        'status',
        'is_default',
        'is_system',
        'created_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'slug',
        'status',
        'is_default',
        'is_system',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'is_system',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
        'is_default' => 'boolean',
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Backward-compatible is_system getter.
     */
    public function getIsSystemAttribute(): bool
    {
        return (bool) ($this->attributes['is_default'] ?? $this->attributes['is_system'] ?? false);
    }

    /**
     * Backward-compatible is_system setter.
     */
    public function setIsSystemAttribute(mixed $value): void
    {
        $this->attributes['is_default'] = (bool) $value;
    }

    /**
     * Scope a query to search by role name or slug.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        $term = trim($search);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%");
        });
    }

    /**
     * Scope a query to filter by active/inactive status.
     */
    public function scopeStatus(Builder $query, mixed $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        $booleanStatus = filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($booleanStatus !== null) {
            return $query->where('status', $booleanStatus);
        }

        return $query;
    }

    /**
     * Scope a query to filter by default/custom role flag.
     */
    public function scopeIsDefault(Builder $query, mixed $isDefault): Builder
    {
        if ($isDefault === null || $isDefault === '') {
            return $query;
        }

        $booleanDefault = filter_var($isDefault, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($booleanDefault !== null) {
            return $query->where('is_default', $booleanDefault);
        }

        return $query;
    }

    /**
     * Scope a query to filter by system/custom role flag (backward compatible).
     */
    public function scopeSystem(Builder $query, mixed $isSystem): Builder
    {
        return $this->scopeIsDefault($query, $isSystem);
    }

    /**
     * Scope a query to only include active roles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to safely sort results.
     */
    public function scopeSort(Builder $query, string $sortBy = 'id', string $sortOrder = 'desc'): Builder
    {
        $column = in_array(strtolower($sortBy), self::ALLOWED_SORT_COLUMNS, true)
            ? strtolower($sortBy)
            : 'id';

        $direction = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction);
    }
}
