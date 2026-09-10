<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commodity extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'commodities';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'commodity_category_id',
        'name',
        'slug',
        'description',
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
        'commodity_category_id',
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
            'commodity_category_id' => 'integer',
            'status' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the parent commodity category.
     *
     * @return BelongsTo<CommodityCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CommodityCategory::class, 'commodity_category_id');
    }

    /**
     * Get the subcategories belonging to this commodity.
     *
     * @return HasMany<CommoditySubcategory, $this>
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(CommoditySubcategory::class, 'commodity_id');
    }

    /**
     * Get the varieties belonging to this commodity.
     *
     * @return HasMany<CommodityVariety, $this>
     */
    public function varieties(): HasMany
    {
        return $this->hasMany(CommodityVariety::class, 'commodity_id');
    }

    /**
     * Get the grades belonging to this commodity.
     *
     * @return HasMany<CommodityGrade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(CommodityGrade::class, 'commodity_id');
    }

    /**
     * Get the administrator who created the commodity.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the administrator who last updated the commodity.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Scope query to only include active commodities.
     *
     * @param  Builder<Commodity>  $query
     * @return Builder<Commodity>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope query to filter by status if supplied.
     *
     * @param  Builder<Commodity>  $query
     * @return Builder<Commodity>
     */
    public function scopeStatus(Builder $query, mixed $status): Builder
    {
        if ($status !== null && $status !== '') {
            $query->where('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /**
     * Scope query to filter by commodity category if supplied.
     *
     * @param  Builder<Commodity>  $query
     * @return Builder<Commodity>
     */
    public function scopeCategory(Builder $query, mixed $categoryId): Builder
    {
        if ($categoryId !== null && $categoryId !== '') {
            $query->where('commodity_category_id', (int) $categoryId);
        }

        return $query;
    }

    /**
     * Scope query to search by name or slug.
     *
     * @param  Builder<Commodity>  $query
     * @return Builder<Commodity>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('slug', 'LIKE', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Scope query to sort by a whitelisted column and order.
     *
     * @param  Builder<Commodity>  $query
     * @return Builder<Commodity>
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
