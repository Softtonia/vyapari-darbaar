<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;

class MediaService
{
    /**
     * Resolve and return a Media model based on provided inputs.
     *
     * @param int|null $mediaId
     * @param UploadedFile|null $file
     * @param string|null $imageUrl
     * @param int|null $attachmentId
     * @return Media|null
     * @throws Exception
     */
    public function resolve(
        ?int $mediaId = null,
        ?UploadedFile $file = null,
        ?string $imageUrl = null,
        ?int $attachmentId = null
    ): ?Media {
        // Reject ambiguous input: Cannot provide both uploaded file and URL
        if ($file && $imageUrl) {
            throw new Exception("Cannot provide both an uploaded file and an image URL.");
        }

        // Case 1: media_id is provided -> use existing Media
        if ($mediaId) {
            $media = Media::find($mediaId);
            if (! $media) {
                throw new Exception("Media with ID {$mediaId} not found.");
            }
            // Optionally update attachment_id if provided and not already set
            if ($attachmentId && !$media->attachment_id) {
                $media->update(['attachment_id' => $attachmentId]);
            }
            return $media;
        }

        // Check if attachment_id exists to prevent duplicates (only if no media_id was provided)
        if ($attachmentId) {
            $existing = Media::where('attachment_id', $attachmentId)->first();
            if ($existing && !$file && !$imageUrl) {
                // If only attachment_id is provided and we found it, reuse it
                return $existing;
            }
        }

        // Case 2: Uploaded file
        if ($file) {
            $this->validateUploadedFile($file);
            $path = $this->storeFile($file);

            return $this->createOrUpdateMedia($attachmentId, $path);
        }

        // Case 3: Image URL
        if ($imageUrl) {
            // Check if the URL matches an already uploaded media record
            $parsedUrl = parse_url($imageUrl, PHP_URL_PATH);

            $existing = Media::where('image_url', $imageUrl)
                ->when($parsedUrl, function($q) use ($parsedUrl) {
                    $q->orWhere('image_url', $parsedUrl)
                      ->orWhere('image_url', 'like', '%' . $parsedUrl);
                })
                ->first();

            if ($existing) {
                // Optionally update attachment_id if it's newly provided during import
                if ($attachmentId && !$existing->attachment_id) {
                    $existing->update(['attachment_id' => $attachmentId]);
                }
                return $existing;
            }

            $this->validateUrl($imageUrl);
            $path = $this->downloadAndStoreImage($imageUrl);

            return $this->createOrUpdateMedia($attachmentId, $path);
        }

        // Case 4: Nothing provided
        return null;
    }

    protected function createOrUpdateMedia(?int $attachmentId, string $path): Media
    {
        if ($attachmentId) {
            $media = Media::where('attachment_id', $attachmentId)->first();
            if ($media) {
                $media->update([
                    'image_url' => Storage::url($path),
                ]);
                return $media;
            }
        }

        // Insert with a temporary attachment_id to satisfy MySQL NOT NULL constraint.
        // We use 0 as a temporary value.
        $media = Media::create([
            'attachment_id' => $attachmentId ?? 0,
            'image_url' => Storage::url($path),
        ]);

        // If no attachment_id was provided, set it to match the generated primary key (id).
        if (!$attachmentId) {
            $media->update(['attachment_id' => $media->id]);
        }

        return $media;
    }

    protected function validateUploadedFile(UploadedFile $file): void
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (! in_array($file->getMimeType(), $allowedMimes)) {
            throw new Exception("Invalid image type: {$file->getMimeType()}");
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new Exception("Image size exceeds 10MB limit.");
        }
    }

    protected function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid image URL format.");
        }
        
        $parsed = parse_url($url);
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            throw new Exception("URL scheme must be http or https.");
        }

        $host = $parsed['host'] ?? '';
        if (in_array($host, ['localhost', '127.0.0.1', '::1']) || str_starts_with($host, '192.168.') || str_starts_with($host, '10.')) {
            throw new Exception("Internal or private network URLs are not allowed.");
        }
    }

    protected function storeFile(UploadedFile $file): string
    {
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('media', $filename, 'public');
    }

    protected function downloadAndStoreImage(string $url): string
    {
        try {
            $response = Http::timeout(15)->get($url);
        } catch (\Exception $e) {
            throw new Exception("Failed to download image from URL: " . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new Exception("Failed to download image from URL (HTTP {$response->status()}).");
        }

        $content = $response->body();
        if (strlen($content) > 10 * 1024 * 1024) {
            throw new Exception("Downloaded image exceeds 10MB limit.");
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($content);
        
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        if (!array_key_exists($mime, $allowedMimes)) {
            throw new Exception("Downloaded content is not a valid image. (Detected: $mime)");
        }

        $ext = $allowedMimes[$mime];
        $filename = Str::random(40) . '.' . $ext;
        $path = 'media/' . $filename;
        
        Storage::disk('public')->put($path, $content);

        return $path;
    }
}
