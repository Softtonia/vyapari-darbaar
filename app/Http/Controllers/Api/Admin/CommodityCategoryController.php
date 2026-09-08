<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommodityCategory\BulkDeleteCommodityCategoryRequest;
use App\Http\Requests\Admin\CommodityCategory\BulkUpdateCommodityCategoryStatusRequest;
use App\Http\Requests\Admin\CommodityCategory\ListCommodityCategoryRequest;
use App\Http\Requests\Admin\CommodityCategory\StoreCommodityCategoryRequest;
use App\Http\Requests\Admin\CommodityCategory\UpdateCommodityCategoryRequest;
use App\Http\Requests\Admin\CommodityCategory\UpdateCommodityCategoryStatusRequest;
use App\Http\Resources\CommodityCategoryOptionResource;
use App\Http\Resources\CommodityCategoryResource;
use App\Models\CommodityCategory;
use App\Services\CommodityCategoryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommodityCategoryController extends Controller
{
    /**
     * Display a paginated listing of commodity categories.
     */
    public function index(ListCommodityCategoryRequest $request, CommodityCategoryService $service): JsonResponse
    {
        $paginator = $service->listCategories($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Commodity categories fetched successfully.',
            'data' => [
                'items' => CommodityCategoryResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active commodity categories for dropdown options.
     */
    public function options(CommodityCategoryService $service): JsonResponse
    {
        $options = $service->getOptions();

        return response()->json([
            'status' => true,
            'message' => 'Commodity category options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created commodity category.
     */
    public function store(StoreCommodityCategoryRequest $request, CommodityCategoryService $service): JsonResponse
    {
        $category = $service->createCategory(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity category created successfully.',
            'data' => new CommodityCategoryResource($category),
        ], 201);
    }

    /**
     * Display the specified commodity category detail.
     */
    public function show(CommodityCategory $commodityCategory): JsonResponse
    {
        $commodityCategory->load(['creator', 'updater']);

        return response()->json([
            'status' => true,
            'message' => 'Commodity category retrieved successfully.',
            'data' => new CommodityCategoryResource($commodityCategory),
        ], 200);
    }

    /**
     * Update the specified commodity category.
     */
    public function update(
        UpdateCommodityCategoryRequest $request,
        CommodityCategory $commodityCategory,
        CommodityCategoryService $service
    ): JsonResponse {
        $category = $service->updateCategory(
            $commodityCategory,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity category updated successfully.',
            'data' => new CommodityCategoryResource($category),
        ], 200);
    }

    /**
     * Update the active/inactive status of a commodity category.
     */
    public function updateStatus(
        UpdateCommodityCategoryStatusRequest $request,
        CommodityCategory $commodityCategory,
        CommodityCategoryService $service
    ): JsonResponse {
        $category = $service->updateStatus(
            $commodityCategory,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity category status updated successfully.',
            'data' => new CommodityCategoryResource($category),
        ], 200);
    }

    /**
     * Bulk update status for multiple commodity categories.
     */
    public function bulkStatus(
        BulkUpdateCommodityCategoryStatusRequest $request,
        CommodityCategoryService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity categories status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single commodity category safely.
     */
    public function destroy(CommodityCategory $commodityCategory, CommodityCategoryService $service): JsonResponse
    {
        try {
            $service->deleteCategory($commodityCategory);

            return response()->json([
                'status' => true,
                'message' => 'Commodity category deleted successfully.',
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
     * Bulk soft delete multiple commodity categories atomically in a single transaction.
     */
    public function bulkDestroy(
        BulkDeleteCommodityCategoryRequest $request,
        CommodityCategoryService $service
    ): JsonResponse {
        $result = $service->bulkDeleteCategories($request->validated('ids'));

        if (! empty($result['blocked_ids'])) {
            return response()->json([
                'status' => false,
                'message' => 'Some commodity categories cannot be deleted because they are in use.',
                'data' => [
                    'blocked_ids' => $result['blocked_ids'],
                ],
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Commodity categories deleted successfully.',
            'data' => [
                'deleted_count' => $result['deleted_count'],
            ],
        ], 200);
    }
}
