<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ListPublicNewsArticleRequest;
use App\Http\Resources\NewsArticleListResource;
use App\Http\Resources\NewsArticleResource;
use App\Http\Resources\NewsCategoryResource;
use App\Http\Resources\NewsSourceResource;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use App\Services\NewsArticleService;
use App\Services\NewsCategoryService;
use App\Services\NewsSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class NewsController extends Controller
{
    /**
     * Display a paginated listing of publicly visible news articles.
     */
    public function index(ListPublicNewsArticleRequest $request, NewsArticleService $service): JsonResponse
    {
        $paginator = $service->listPublicArticles($request->validated());

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
     * Display the full details of a single public news article by slug.
     */
    public function show(string $slug, NewsArticleService $service): JsonResponse
    {
        $article = $service->getPublicArticleBySlug($slug);

        if (! $article) {
            return response()->json([
                'status' => false,
                'message' => 'News article not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'News article retrieved successfully.',
            'data' => new NewsArticleResource($article),
        ], 200);
    }

    /**
     * Get active news categories for public navigation/filtering (Redis cached).
     */
    public function categories(NewsCategoryService $service): JsonResponse
    {
        $categories = Cache::remember(NewsCategoryService::OPTIONS_CACHE_KEY . ':public', 3600, function () {
            return NewsCategory::query()
                ->active()
                ->ordered()
                ->select(['id', 'name', 'slug', 'description', 'sort_order'])
                ->get();
        });

        return response()->json([
            'status' => true,
            'message' => 'News categories retrieved successfully.',
            'data' => NewsCategoryResource::collection($categories),
        ], 200);
    }

    /**
     * Get active news sources for public navigation/filtering (Redis cached).
     */
    public function sources(NewsSourceService $service): JsonResponse
    {
        $sources = Cache::remember(NewsSourceService::OPTIONS_CACHE_KEY . ':public', 3600, function () {
            return NewsSource::query()
                ->active()
                ->ordered()
                ->select(['id', 'name', 'slug', 'code', 'website_url', 'logo', 'description', 'sort_order'])
                ->get();
        });

        return response()->json([
            'status' => true,
            'message' => 'News sources retrieved successfully.',
            'data' => NewsSourceResource::collection($sources),
        ], 200);
    }
}
