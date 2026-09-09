<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommodityVariety extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'commodity_varieties';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'commodity_id',
        'commodity_subcategory_id',
        'name_en',
        'name_hi',
        'slug',
        'description_en',
        'description_hi',
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
        'commodity_id',
        'commodity_subcategory_id',
        'name_en',
        'name_hi',
        'slug',
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
            'commodity_id' => 'integer',
            'commodity_subcategory_id' => 'integer',
            'status' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the parent commodity.
     *
     * @return BelongsTo<Commodity, $this>
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    /**
     * Get the optional parent commodity subcategory.
     *
     * @return BelongsTo<CommoditySubcategory, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(CommoditySubcategory::class, 'commodity_subcategory_id');
    }

    /**
     * Get the administrator who created the commodity variety.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the administrator who last updated the commodity variety.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Scope query to only include active commodity varieties.
     *
     * @param  Builder<CommodityVariety>  $query
     * @return Builder<CommodityVariety>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope query to filter by status if supplied.
     *
     * @param  Builder<CommodityVariety>  $query
     * @return Builder<CommodityVariety>
     */
    public function scopeStatus(Builder $query, mixed $status): Builder
    {
        if ($status !== null && $status !== '') {
            $query->where('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /**
     * Scope query to filter by commodity if supplied.
     *
     * @param  Builder<CommodityVariety>  $query
     * @return Builder<CommodityVariety>
     */
    public function scopeCommodity(Builder $query, mixed $commodityId): Builder
    {
        if ($commodityId !== null && $commodityId !== '') {
            $query->where('commodity_id', (int) $commodityId);
        }

        return $query;
    }

    /**
     * Scope query to filter by commodity subcategory if supplied.
     *
     * @param  Builder<CommodityVariety>  $query
     * @return Builder<CommodityVariety>
     */
    public function scopeSubcategory(Builder $query, mixed $subcategoryId): Builder
    {
        if ($subcategoryId !== null && $subcategoryId !== '') {
            $query->where('commodity_subcategory_id', (int) $subcategoryId);
        }

        return $query;
    }

    /**
     * Scope query to filter by commodity category (through parent commodity) if supplied.
     *
     * @param  Builder<CommodityVariety>  $query
     * @return Builder<CommodityVariety>
     */
    public function scopeCategory(Builder $query, mixed $categoryId): Builder
    {
        if ($categoryId !== null && $categoryId !== '') {
            $query->whereHas('commodity', function (Builder $q) use ($categoryId) {
                $q->where('commodity_category_id', (int) $categoryId);
            });
        }

        return $query;
    }

    /**
     * Scope query to search by English name, Hindi name, or slug.
     *
     * @param  Builder<CommodityVariety>  $query
     * @return Builder<CommodityVariety>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name_en', 'LIKE', "%{$term}%")
                    ->orWhere('name_hi', 'LIKE', "%{$term}%")
                    ->orWhere('slug', 'LIKE', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Scope query to sort by a whitelisted column and order.
     *
     * @param  Builder<CommodityVariety>  $query
     * @return Builder<CommodityVariety>
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
