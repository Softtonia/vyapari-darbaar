<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function index(Request $request)
    {
        $query = Media::query();

        if ($request->filled('attachment_id')) {
            $query->where('attachment_id', $request->integer('attachment_id'));
        }

        return response()->json([
            'status' => true,
            'data' => $query->latest()->paginate(
                $request->integer('per_page', 20)
            ),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'attachment_id' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'file', 'mimes:jpeg,png,gif,webp', 'max:10240'],
            'file' => ['nullable', 'file', 'mimes:jpeg,png,gif,webp', 'max:10240'],
            'image_url' => ['nullable', 'url'],
        ]);

        if (!$request->hasFile('image') && !$request->hasFile('file') && !$request->filled('image_url')) {
            return response()->json([
                'status' => false,
                'message' => 'Either an image file or an image_url is required.'
            ], 422);
        }

        try {
            $uploadedFile = $request->file('image') ?: $request->file('file');
            
            $media = $this->mediaService->resolve(
                null,
                $uploadedFile,
                $request->input('image_url'),
                $request->input('attachment_id')
            );

            return response()->json([
                'status' => true,
                'message' => 'Media created successfully.',
                'data' => $media,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function show(Media $media)
    {
        return response()->json([
            'status' => true,
            'data' => $media,
        ]);
    }

    public function update(Request $request, Media $media)
    {
        $request->validate([
            'attachment_id' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'file', 'mimes:jpeg,png,gif,webp', 'max:10240'],
            'file' => ['nullable', 'file', 'mimes:jpeg,png,gif,webp', 'max:10240'],
            'image_url' => ['nullable', 'url'],
        ]);

        try {
            if ($request->hasFile('image') || $request->hasFile('file') || $request->filled('image_url')) {
                // Delete old physical file if replacing
                if ($media->image_url && Storage::disk('public')->exists(str_replace('/storage/', '', $media->image_url))) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $media->image_url));
                }

                $uploadedFile = $request->file('image') ?: $request->file('file');

                // Resolve will handle upload/download and update the media record if attachment_id matches, 
                // but since we are specifically updating this media record, we should handle it carefully.
                // It's safer to just process the new file/url and update the current model.
                $newMedia = $this->mediaService->resolve(
                    null,
                    $uploadedFile,
                    $request->input('image_url'),
                    null // Don't pass attachment ID to prevent resolving a different media record
                );
                
                $media->update([
                    'image_url' => $newMedia->image_url,
                    'attachment_id' => $request->input('attachment_id', $media->attachment_id)
                ]);

                // The new media record created by resolve was temporary or duplicate?
                // Wait, if resolve creates a new record, we now have an orphaned record.
                // Let's delete the newly created one to avoid orphans, or adjust the service.
                if ($newMedia && $newMedia->id !== $media->id) {
                    $newMedia->delete(); // Remove the DB record, keep the file because we copied the URL
                }

            } elseif ($request->has('attachment_id')) {
                $media->update(['attachment_id' => $request->input('attachment_id')]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Media updated successfully.',
                'data' => $media->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(Media $media)
    {
        if ($media->image_url && Storage::disk('public')->exists(str_replace('/storage/', '', $media->image_url))) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $media->image_url));
        }

        $media->delete();

        return response()->json([
            'status' => true,
            'message' => 'Media deleted successfully.',
        ]);
    }
}
