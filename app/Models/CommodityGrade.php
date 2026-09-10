<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommodityGrade extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'commodity_grades';

    /**
     * Allowed columns for dynamic sorting whitelist.
     *
     * @var list<string>
     */
    public const ALLOWED_SORT_COLUMNS = [
        'id',
        'name',
        'slug',
        'commodity_id',
        'commodity_subcategory_id',
        'commodity_variety_id',
        'sort_order',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'commodity_id',
        'commodity_subcategory_id',
        'commodity_variety_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'commodity_id' => 'integer',
        'commodity_subcategory_id' => 'integer',
        'commodity_variety_id' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(CommoditySubcategory::class, 'commodity_subcategory_id');
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CommodityVariety::class, 'commodity_variety_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeStatus($query, mixed $status)
    {
        if ($status !== null && $status !== '') {
            $query->where('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    public function scopeCommodity($query, mixed $commodityId)
    {
        if ($commodityId !== null && $commodityId !== '') {
            $query->where('commodity_id', (int) $commodityId);
        }

        return $query;
    }

    public function scopeSubcategory($query, mixed $subcategoryId)
    {
        if ($subcategoryId !== null && $subcategoryId !== '') {
            $query->where('commodity_subcategory_id', (int) $subcategoryId);
        }

        return $query;
    }

    public function scopeVariety($query, mixed $varietyId)
    {
        if ($varietyId !== null && $varietyId !== '') {
            $query->where('commodity_variety_id', (int) $varietyId);
        }

        return $query;
    }

    public function scopeCategory($query, mixed $categoryId)
    {
        if ($categoryId !== null && $categoryId !== '') {
            $query->whereHas('commodity', function ($q) use ($categoryId) {
                $q->where('commodity_category_id', (int) $categoryId);
            });
        }

        return $query;
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('slug', 'LIKE', "%{$term}%");
            });
        }

        return $query;
    }

    public function scopeSorted($query, ?string $sortBy = 'sort_order', ?string $sortOrder = 'asc')
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

        if ($sortBy !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }
}
