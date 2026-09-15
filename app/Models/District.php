<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class District extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'districts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'state_id',
        'name',
        'slug',
        'code',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Allowed columns for dynamic sorting whitelist.
     *
     * @var list<string>
     */
    public const ALLOWED_SORT_COLUMNS = [
        'id',
        'name',
        'slug',
        'code',
        'state_id',
        'sort_order',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state_id' => 'integer',
            'status' => 'boolean',
            'sort_order' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * Get the parent state.
     *
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    /**
     * Get the mandis belonging to this district.
     *
     * @return HasMany<Mandi, $this>
     */
    public function mandis(): HasMany
    {
        return $this->hasMany(Mandi::class, 'district_id');
    }

    /**
     * Get the administrator who created the district.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the administrator who last updated the district.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Scope query to only include active districts.
     *
     * @param  Builder<District>  $query
     * @return Builder<District>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope query to filter by state ID if supplied.
     *
     * @param  Builder<District>  $query
     * @return Builder<District>
     */
    public function scopeState(Builder $query, mixed $stateId): Builder
    {
        if ($stateId !== null && $stateId !== '') {
            $query->where('state_id', (int) $stateId);
        }

        return $query;
    }

    /**
     * Scope query to filter by status if supplied.
     *
     * @param  Builder<District>  $query
     * @return Builder<District>
     */
    public function scopeStatus(Builder $query, mixed $status): Builder
    {
        if ($status !== null && $status !== '') {
            $query->where('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /**
     * Scope query to search by name, code, or slug.
     *
     * @param  Builder<District>  $query
     * @return Builder<District>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('code', 'LIKE', "%{$term}%")
                    ->orWhere('slug', 'LIKE', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Scope query to sort by a whitelisted column and order.
     *
     * @param  Builder<District>  $query
     * @return Builder<District>
     */
    public function scopeSorted(Builder $query, ?string $sortBy = 'sort_order', ?string $sortOrder = 'asc'): Builder
    {
        $sortBy = strtolower(trim((string) $sortBy));
        $sortOrder = strtolower(trim((string) $sortOrder));

        if (! in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'sort_order';
        }

        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }

        $query->orderBy($sortBy, $sortOrder);

        // Secondary stable sort
        if ($sortBy !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }
}
