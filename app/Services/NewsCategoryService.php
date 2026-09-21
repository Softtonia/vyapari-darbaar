<?php

namespace App\Services;

use App\Models\NewsCategory;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NewsCategoryService
{
    public const OPTIONS_CACHE_KEY = 'news_categories:options';
    public const OPTIONS_CACHE_TTL = 3600; // 1 hour

    /**
     * List news categories with pagination and filtering for admin.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listCategories(array $filters): LengthAwarePaginator
    {
        $query = NewsCategory::query();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== null) {
            $query->where('status', (bool) $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortOrder = strtolower($filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortBy, $sortOrder)->orderBy('id', 'asc');

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active news categories for dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(): array
    {
        return Cache::remember(self::OPTIONS_CACHE_KEY, self::OPTIONS_CACHE_TTL, function () {
            return NewsCategory::query()
                ->active()
                ->ordered()
                ->select(['id', 'name', 'slug', 'description'])
                ->get()
                ->map(fn (NewsCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                ])
                ->all();
        });
    }

    /**
     * Create a new news category.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCategory(array $data, ?int $adminId = null): NewsCategory
    {
        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : $this->generateSlug($data['name']);

        $category = NewsCategory::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => array_key_exists('status', $data) ? (bool) $data['status'] : true,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);

        $this->invalidateOptionsCache();

        return $category;
    }

    /**
     * Update an existing news category.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(NewsCategory $category, array $data, ?int $adminId = null): NewsCategory
    {
        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            if (! empty($data['slug'])) {
                $data['slug'] = Str::slug($data['slug']);
            } elseif (array_key_exists('name', $data) && $data['name'] !== $category->name && empty($category->slug)) {
                $data['slug'] = $this->generateSlug($data['name'], $category->id);
            }
        }

        $data['updated_by'] = $adminId;

        $category->update($data);

        $this->invalidateOptionsCache();

        return $category->fresh();
    }

    /**
     * Update the status of a news category.
     */
    public function updateStatus(NewsCategory $category, bool $status, ?int $adminId = null): NewsCategory
    {
        $category->update([
            'status' => $status,
            'updated_by' => $adminId,
        ]);

        $this->invalidateOptionsCache();

        return $category;
    }

    /**
     * Safely soft delete a news category. Throws DomainException if non-deleted articles reference it.
     *
     * @throws DomainException
     */
    public function deleteCategory(NewsCategory $category): void
    {
        $articlesCount = $category->articles()->whereNull('deleted_at')->count();
        if ($articlesCount > 0) {
            throw new DomainException("Cannot delete news category because it is referenced by {$articlesCount} active article(s).");
        }

        $category->delete();

        $this->invalidateOptionsCache();
    }

    /**
     * Invalidate options cache.
     */
    public function invalidateOptionsCache(): void
    {
        Cache::forget(self::OPTIONS_CACHE_KEY);
    }

    /**
     * Generate unique slug for news category.
     */
    public function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'category';
        $slug = $base;
        $counter = 1;

        while (NewsCategory::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $counter++;
            $slug = "{$base}-{$counter}";
        }

        return $slug;
    }
}
