<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsSource\ListNewsSourceRequest;
use App\Http\Requests\Admin\NewsSource\StoreNewsSourceRequest;
use App\Http\Requests\Admin\NewsSource\UpdateNewsSourceRequest;
use App\Http\Requests\Admin\NewsSource\UpdateNewsSourceStatusRequest;
use App\Http\Resources\NewsSourceResource;
use App\Models\NewsSource;
use App\Services\NewsSourceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsSourceController extends Controller
{
    /**
     * Display a paginated list of news sources.
     */
    public function index(ListNewsSourceRequest $request, NewsSourceService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-sources.view');

        $paginator = $service->listSources($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'News sources fetched successfully.',
            'data' => [
                'items' => NewsSourceResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active news sources for dropdowns.
     */
    public function options(Request $request, NewsSourceService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-sources.view');

        $options = $service->getOptions();

        return response()->json([
            'status' => true,
            'message' => 'News source options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created news source.
     */
    public function store(StoreNewsSourceRequest $request, NewsSourceService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-sources.create');

        $source = $service->createSource($request->validated(), $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'News source created successfully.',
            'data' => new NewsSourceResource($source),
        ], 201);
    }

    /**
     * Display the specified news source.
     */
    public function show(Request $request, NewsSource $newsSource): JsonResponse
    {
        $this->authorizePermission($request, 'news-sources.view');

        return response()->json([
            'status' => true,
            'message' => 'News source retrieved successfully.',
            'data' => new NewsSourceResource($newsSource),
        ], 200);
    }

    /**
     * Update the specified news source.
     */
    public function update(UpdateNewsSourceRequest $request, NewsSource $newsSource, NewsSourceService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-sources.update');

        $source = $service->updateSource($newsSource, $request->validated(), $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'News source updated successfully.',
            'data' => new NewsSourceResource($source),
        ], 200);
    }

    /**
     * Update active/inactive status of the news source.
     */
    public function updateStatus(UpdateNewsSourceStatusRequest $request, NewsSource $newsSource, NewsSourceService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-sources.update');

        $source = $service->updateStatus($newsSource, (bool) $request->validated('status'), $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'News source status updated successfully.',
            'data' => new NewsSourceResource($source),
        ], 200);
    }

    /**
     * Delete a news source safely (409 if in use).
     */
    public function destroy(Request $request, NewsSource $newsSource, NewsSourceService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-sources.delete');

        try {
            $service->deleteSource($newsSource);

            return response()->json([
                'status' => true,
                'message' => 'News source deleted successfully.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'SOURCE_IN_USE',
            ], 409);
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
