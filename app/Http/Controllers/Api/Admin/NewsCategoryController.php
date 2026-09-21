<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsCategory\ListNewsCategoryRequest;
use App\Http\Requests\Admin\NewsCategory\StoreNewsCategoryRequest;
use App\Http\Requests\Admin\NewsCategory\UpdateNewsCategoryRequest;
use App\Http\Requests\Admin\NewsCategory\UpdateNewsCategoryStatusRequest;
use App\Http\Resources\NewsCategoryResource;
use App\Models\NewsCategory;
use App\Services\NewsCategoryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsCategoryController extends Controller
{
    /**
     * Display a paginated list of news categories.
     */
    public function index(ListNewsCategoryRequest $request, NewsCategoryService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-categories.view');

        $paginator = $service->listCategories($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'News categories fetched successfully.',
            'data' => [
                'items' => NewsCategoryResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active news categories for dropdowns.
     */
    public function options(Request $request, NewsCategoryService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-categories.view');

        $options = $service->getOptions();

        return response()->json([
            'status' => true,
            'message' => 'News category options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created news category.
     */
    public function store(StoreNewsCategoryRequest $request, NewsCategoryService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-categories.create');

        $category = $service->createCategory($request->validated(), $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'News category created successfully.',
            'data' => new NewsCategoryResource($category),
        ], 201);
    }

    /**
     * Display the specified news category.
     */
    public function show(Request $request, NewsCategory $newsCategory): JsonResponse
    {
        $this->authorizePermission($request, 'news-categories.view');

        return response()->json([
            'status' => true,
            'message' => 'News category retrieved successfully.',
            'data' => new NewsCategoryResource($newsCategory),
        ], 200);
    }

    /**
     * Update the specified news category.
     */
    public function update(UpdateNewsCategoryRequest $request, NewsCategory $newsCategory, NewsCategoryService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-categories.update');

        $category = $service->updateCategory($newsCategory, $request->validated(), $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'News category updated successfully.',
            'data' => new NewsCategoryResource($category),
        ], 200);
    }

    /**
     * Update active/inactive status of the news category.
     */
    public function updateStatus(UpdateNewsCategoryStatusRequest $request, NewsCategory $newsCategory, NewsCategoryService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-categories.update');

        $category = $service->updateStatus($newsCategory, (bool) $request->validated('status'), $request->user()?->id);

        return response()->json([
            'status' => true,
            'message' => 'News category status updated successfully.',
            'data' => new NewsCategoryResource($category),
        ], 200);
    }

    /**
     * Delete a news category safely (409 if in use).
     */
    public function destroy(Request $request, NewsCategory $newsCategory, NewsCategoryService $service): JsonResponse
    {
        $this->authorizePermission($request, 'news-categories.delete');

        try {
            $service->deleteCategory($newsCategory);

            return response()->json([
                'status' => true,
                'message' => 'News category deleted successfully.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'CATEGORY_IN_USE',
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
