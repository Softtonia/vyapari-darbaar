<?php

namespace App\Models;

use App\Enums\MarketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mandi extends Model
{
    use \App\Traits\AutoSortOrder;

    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mandis';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'district_id',
        'name',
        'slug',
        'code',
        'market_type',
        'address',
        'pincode',
        'latitude',
        'longitude',
        'contact_phone',
        'contact_email',
        'website',
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
        'district_id',
        'market_type',
        'pincode',
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
            'district_id' => 'integer',
            'market_type' => MarketType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => 'boolean',
            'sort_order' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * Get the parent district.
     *
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    /**
     * Get the administrator who created the mandi.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the administrator who last updated the mandi.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Scope query to only include active mandis.
     *
     * @param  Builder<Mandi>  $query
     * @return Builder<Mandi>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope query to filter by district ID if supplied.
     *
     * @param  Builder<Mandi>  $query
     * @return Builder<Mandi>
     */
    public function scopeDistrict(Builder $query, mixed $districtId): Builder
    {
        if ($districtId !== null && $districtId !== '') {
            $query->where('district_id', (int) $districtId);
        }

        return $query;
    }

    /**
     * Scope query to filter by state ID through parent district.
     *
     * @param  Builder<Mandi>  $query
     * @return Builder<Mandi>
     */
    public function scopeState(Builder $query, mixed $stateId): Builder
    {
        if ($stateId !== null && $stateId !== '') {
            $query->whereHas('district', function (Builder $q) use ($stateId) {
                $q->where('state_id', (int) $stateId);
            });
        }

        return $query;
    }

    /**
     * Scope query to filter by market type if supplied.
     *
     * @param  Builder<Mandi>  $query
     * @return Builder<Mandi>
     */
    public function scopeMarketType(Builder $query, mixed $type): Builder
    {
        if ($type !== null && $type !== '') {
            $val = $type instanceof MarketType ? $type->value : (string) $type;
            $query->where('market_type', $val);
        }

        return $query;
    }

    /**
     * Scope query to filter by exact pincode if supplied.
     *
     * @param  Builder<Mandi>  $query
     * @return Builder<Mandi>
     */
    public function scopePincode(Builder $query, mixed $pincode): Builder
    {
        if ($pincode !== null && $pincode !== '') {
            $query->where('pincode', trim((string) $pincode));
        }

        return $query;
    }

    /**
     * Scope query to filter by status if supplied.
     *
     * @param  Builder<Mandi>  $query
     * @return Builder<Mandi>
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
     * @param  Builder<Mandi>  $query
     * @return Builder<Mandi>
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
     * @param  Builder<Mandi>  $query
     * @return Builder<Mandi>
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
