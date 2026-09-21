<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\NewsStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\News\ListAdminNewsArticleRequest;
use App\Http\Requests\Admin\News\StoreNewsArticleRequest;
use App\Http\Requests\Admin\News\UpdateNewsArticleRequest;
use App\Http\Requests\Admin\News\UpdateNewsArticleStatusRequest;
use App\Http\Requests\Admin\News\UpdateNewsBreakingRequest;
use App\Http\Requests\Admin\News\UpdateNewsFeaturedRequest;
use App\Http\Resources\NewsArticleListResource;
use App\Http\Resources\NewsArticleResource;
use App\Models\NewsArticle;
use App\Services\NewsArticleService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsArticleController extends Controller
{
    /**
     * Display a paginated listing of news articles for admin.
     */
    public function index(ListAdminNewsArticleRequest $request, NewsArticleService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news.view');

        $paginator = $service->listAdminArticles($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'News articles fetched successfully.',
            'data' => [
                'items' => NewsArticleListResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a newly created news article.
     */
    public function store(StoreNewsArticleRequest $request, NewsArticleService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news.create');

        $status = $request->input('status');
        if (in_array($status, [NewsStatus::PUBLISHED->value, NewsStatus::SCHEDULED->value], true)) {
            $this->authorizePermission($request, 'news.publish');
        }

        try {
            $article = $service->createArticle($request->validated(), $request->user()?->id);

            return response()->json([
                'status' => true,
                'message' => 'News article created successfully.',
                'data' => new NewsArticleResource($article),
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified news article.
     */
    public function show(Request $request, NewsArticle $newsArticle): JsonResponse
    {
        $this->authorizePermission($request, 'news.view');

        $newsArticle->load(['source', 'category', 'media', 'creator', 'updater']);

        return response()->json([
            'status' => true,
            'message' => 'News article retrieved successfully.',
            'data' => new NewsArticleResource($newsArticle),
        ], 200);
    }

    /**
     * Update the specified news article.
     */
    public function update(UpdateNewsArticleRequest $request, NewsArticle $newsArticle, NewsArticleService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news.update');

        if ($request->has('status')) {
            $newStatus = $request->input('status');
            if (in_array($newStatus, [NewsStatus::PUBLISHED->value, NewsStatus::SCHEDULED->value], true)) {
                $this->authorizePermission($request, 'news.publish');
            }
        }

        try {
            $article = $service->updateArticle($newsArticle, $request->validated(), $request->user()?->id);

            return response()->json([
                'status' => true,
                'message' => 'News article updated successfully.',
                'data' => new NewsArticleResource($article),
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update the publication status of the article.
     */
    public function updateStatus(UpdateNewsArticleStatusRequest $request, NewsArticle $newsArticle, NewsArticleService $service): JsonResponse
    {
        $targetStatus = $request->validated('status');

        if (in_array($targetStatus, [NewsStatus::PUBLISHED->value, NewsStatus::SCHEDULED->value], true)) {
            $this->authorizePermission($request, 'news.publish');
        } else {
            $this->authorizePermission($request, 'news.update');
        }

        try {
            $article = $service->updateStatus(
                $newsArticle,
                $targetStatus,
                $request->user()?->id,
                $request->validated('scheduled_at'),
                $request->validated('published_at')
            );

            return response()->json([
                'status' => true,
                'message' => "News article status updated to {$targetStatus} successfully.",
                'data' => new NewsArticleResource($article->load(['source', 'category'])),
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle or update the is_featured flag.
     */
    public function updateFeatured(UpdateNewsFeaturedRequest $request, NewsArticle $newsArticle, NewsArticleService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news.update');

        $article = $service->updateFeatured($newsArticle, (bool) $request->validated('is_featured'), $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'News article featured status updated successfully.',
            'data' => new NewsArticleResource($article),
        ], 200);
    }

    /**
     * Toggle or update the is_breaking flag.
     */
    public function updateBreaking(UpdateNewsBreakingRequest $request, NewsArticle $newsArticle, NewsArticleService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news.update');

        $article = $service->updateBreaking($newsArticle, (bool) $request->validated('is_breaking'), $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'News article breaking status updated successfully.',
            'data' => new NewsArticleResource($article),
        ], 200);
    }

    /**
     * Soft delete the specified news article.
     */
    public function destroy(Request $request, NewsArticle $newsArticle, NewsArticleService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news.delete');

        $service->deleteArticle($newsArticle);

        return response()->json([
            'status' => true,
            'message' => 'News article deleted successfully.',
        ], 200);
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
