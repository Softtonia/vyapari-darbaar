<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsMedia\StoreNewsMediaRequest;
use App\Http\Resources\NewsMediaResource;
use App\Models\NewsArticle;
use App\Models\NewsMedia;
use App\Services\NewsMediaService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsMediaController extends Controller
{
    /**
     * Store a new media attachment for the article.
     */
    public function store(StoreNewsMediaRequest $request, NewsArticle $article, NewsMediaService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news.update');

        $media = $service->addMedia($article, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'News media attachment added successfully.',
            'data' => new NewsMediaResource($media),
        ], 201);
    }

    /**
     * Remove a media attachment from the article.
     */
    public function destroy(Request $request, NewsArticle $article, NewsMedia $media, NewsMediaService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news.update');

        try {
            $service->deleteMedia($article, $media);

            return response()->json([
                'status' => true,
                'message' => 'News media attachment deleted successfully.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Helper for Spatie permission enforcement.
     */
    protected function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && ! $user->can($permission) && ! $user->hasRole('super_admin')) {
            abort(response()->json([
                'status' => false,
                'message' => "Unauthorized action. Missing {$permission} permission.",
            ], 403));
        }
    }
}
