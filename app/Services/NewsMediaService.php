<?php

namespace App\Services;

use App\Enums\NewsMediaType;
use App\Models\NewsArticle;
use App\Models\NewsMedia;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsMediaService
{
    public const STORAGE_DISK = 'public';

    /**
     * Add a media attachment to a news article.
     *
     * @param  array<string, mixed>  $data
     */
    public function addMedia(NewsArticle $article, array $data): NewsMedia
    {
        $filePath = null;
        $mimeType = null;
        $fileSize = null;

        $mediaType = $data['media_type'] instanceof NewsMediaType
            ? $data['media_type']->value
            : (string) $data['media_type'];

        if (isset($data['file']) && $data['file'] instanceof UploadedFile) {
            /** @var UploadedFile $file */
            $file = $data['file'];
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            $subDir = $mediaType === NewsMediaType::IMAGE->value ? 'images' : 'documents';
            $storageDir = "news/{$article->id}/{$subDir}";

            $filename = Str::random(32) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs($storageDir, $filename, self::STORAGE_DISK);
        }

        $media = NewsMedia::create([
            'news_article_id' => $article->id,
            'media_type' => $mediaType,
            'language' => $data['language'] ?? null,
            'title' => $data['title'] ?? null,
            'file_path' => $filePath,
            'external_url' => $data['external_url'] ?? null,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->invalidateArticleCache($article);

        return $media;
    }

    /**
     * Delete media record and remove its local file safely.
     *
     * @throws DomainException
     */
    public function deleteMedia(NewsArticle $article, NewsMedia $media): void
    {
        // Enforce strict article ownership
        if ($media->news_article_id !== $article->id) {
            throw new DomainException('The requested media does not belong to the specified article.');
        }

        $filePath = $media->file_path;

        $media->delete();

        if ($filePath && ! filter_var($filePath, FILTER_VALIDATE_URL)) {
            Storage::disk(self::STORAGE_DISK)->delete($filePath);
        }

        $this->invalidateArticleCache($article);
    }

    /**
     * Invalidate detail cache for the article.
     */
    protected function invalidateArticleCache(NewsArticle $article): void
    {
        if (! empty($article->slug)) {
            Cache::forget(NewsArticleService::DETAIL_CACHE_PREFIX . $article->slug);
        }
    }
}
