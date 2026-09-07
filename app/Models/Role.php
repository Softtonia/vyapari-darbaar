<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $status
 * @property bool $is_system
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static Builder|Role query()
 * @method static Builder|Role search(?string $search)
 * @method static Builder|Role status(mixed $status)
 * @method static Builder|Role system(mixed $isSystem)
 * @method static Builder|Role active()
 * @method static Builder|Role sort(string $sortBy = 'id', string $sortOrder = 'desc')
 */
class Role extends Model
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
        'slug',
        'status',
        'created_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'status',
        'is_system',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

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
     * Scope a query to filter by system/custom role flag.
     */
    public function scopeSystem(Builder $query, mixed $isSystem): Builder
    {
        if ($isSystem === null || $isSystem === '') {
            return $query;
        }

        $booleanSystem = filter_var($isSystem, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($booleanSystem !== null) {
            return $query->where('is_system', $booleanSystem);
        }

        return $query;
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
