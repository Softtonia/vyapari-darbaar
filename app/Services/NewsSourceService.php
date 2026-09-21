<?php

namespace App\Services;

use App\Models\NewsSource;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsSourceService
{
    public const OPTIONS_CACHE_KEY = 'news_sources:options';
    public const OPTIONS_CACHE_TTL = 3600; // 1 hour
    public const STORAGE_DISK = 'public';
    public const LOGO_DIR = 'news-sources/logos';

    /**
     * List news sources with pagination and filtering for admin.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listSources(array $filters): LengthAwarePaginator
    {
        $query = NewsSource::query();

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
     * Get lightweight, cached list of active news sources for dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(): array
    {
        return Cache::remember(self::OPTIONS_CACHE_KEY, self::OPTIONS_CACHE_TTL, function () {
            return NewsSource::query()
                ->active()
                ->ordered()
                ->select(['id', 'name', 'code', 'slug', 'logo'])
                ->get()
                ->map(fn (NewsSource $source) => [
                    'id' => $source->id,
                    'name' => $source->name,
                    'code' => $source->code,
                    'slug' => $source->slug,
                    'logo' => $source->logo_url,
                ])
                ->all();
        });
    }

    /**
     * Create a new news source.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSource(array $data, ?int $adminId = null): NewsSource
    {
        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : $this->generateSlug($data['name']);

        $logoPath = null;
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $logoPath = $data['logo']->store(self::LOGO_DIR, self::STORAGE_DISK);
        } elseif (isset($data['logo']) && is_string($data['logo'])) {
            $logoPath = $data['logo'];
        }

        $source = NewsSource::create([
            'name' => $data['name'],
            'slug' => $slug,
            'code' => $data['code'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'logo' => $logoPath,
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => array_key_exists('status', $data) ? (bool) $data['status'] : true,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);

        $this->invalidateOptionsCache();

        return $source;
    }

    /**
     * Update an existing news source.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSource(NewsSource $source, array $data, ?int $adminId = null): NewsSource
    {
        $oldLogo = $source->logo;

        if (array_key_exists('name', $data) || array_key_exists('slug', $data)) {
            if (! empty($data['slug'])) {
                $data['slug'] = Str::slug($data['slug']);
            } elseif (array_key_exists('name', $data) && $data['name'] !== $source->name && empty($source->slug)) {
                $data['slug'] = $this->generateSlug($data['name'], $source->id);
            }
        }

        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $data['logo'] = $data['logo']->store(self::LOGO_DIR, self::STORAGE_DISK);
            if ($oldLogo && ! filter_var($oldLogo, FILTER_VALIDATE_URL)) {
                Storage::disk(self::STORAGE_DISK)->delete($oldLogo);
            }
        }

        $data['updated_by'] = $adminId;

        $source->update($data);

        $this->invalidateOptionsCache();

        return $source->fresh();
    }

    /**
     * Update the status of a news source.
     */
    public function updateStatus(NewsSource $source, bool $status, ?int $adminId = null): NewsSource
    {
        $source->update([
            'status' => $status,
            'updated_by' => $adminId,
        ]);

        $this->invalidateOptionsCache();

        return $source;
    }

    /**
     * Safely soft delete a news source. Throws DomainException if non-deleted articles reference it.
     *
     * @throws DomainException
     */
    public function deleteSource(NewsSource $source): void
    {
        $articlesCount = $source->articles()->whereNull('deleted_at')->count();
        if ($articlesCount > 0) {
            throw new DomainException("Cannot delete news source because it is referenced by {$articlesCount} active article(s).");
        }

        $source->delete();

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
     * Generate unique slug for news source.
     */
    public function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'source';
        $slug = $base;
        $counter = 1;

        while (NewsSource::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $counter++;
            $slug = "{$base}-{$counter}";
        }

        return $slug;
    }
}
