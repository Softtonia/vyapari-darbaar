<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommoditySubcategory\BulkDeleteCommoditySubcategoryRequest;
use App\Http\Requests\Admin\CommoditySubcategory\BulkUpdateCommoditySubcategoryStatusRequest;
use App\Http\Requests\Admin\CommoditySubcategory\ListCommoditySubcategoryRequest;
use App\Http\Requests\Admin\CommoditySubcategory\StoreCommoditySubcategoryRequest;
use App\Http\Requests\Admin\CommoditySubcategory\UpdateCommoditySubcategoryRequest;
use App\Http\Requests\Admin\CommoditySubcategory\UpdateCommoditySubcategoryStatusRequest;
use App\Http\Resources\CommoditySubcategoryListResource;
use App\Http\Resources\CommoditySubcategoryResource;
use App\Models\CommoditySubcategory;
use App\Services\CommoditySubcategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommoditySubcategoryController extends Controller
{
    /**
     * Display a paginated listing of commodity subcategories.
     */
    public function index(ListCommoditySubcategoryRequest $request, CommoditySubcategoryService $service): JsonResponse
    {
        $paginator = $service->listCommoditySubcategories($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategories fetched successfully.',
            'data' => [
                'items' => CommoditySubcategoryListResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active commodity subcategories for dropdown options.
     */
    public function options(Request $request, CommoditySubcategoryService $service): JsonResponse
    {
        $commodityId = $request->query('commodity_id');
        $commodityId = ($commodityId !== null && $commodityId !== '') ? (int) $commodityId : null;

        $options = $service->getOptions($commodityId);

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategory options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created commodity subcategory.
     */
    public function store(StoreCommoditySubcategoryRequest $request, CommoditySubcategoryService $service): JsonResponse
    {
        $subcat = $service->createCommoditySubcategory(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategory created successfully.',
            'data' => new CommoditySubcategoryResource($subcat),
        ], 201);
    }

    /**
     * Display the specified commodity subcategory detail.
     */
    public function show(CommoditySubcategory $commoditySubcategory): JsonResponse
    {
        $commoditySubcategory->load([
            'commodity:id,commodity_category_id,name_en,name_hi,slug',
            'commodity.category:id,name_en,name_hi,slug',
            'creator',
            'updater',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategory retrieved successfully.',
            'data' => new CommoditySubcategoryResource($commoditySubcategory),
        ], 200);
    }

    /**
     * Update the specified commodity subcategory.
     */
    public function update(
        UpdateCommoditySubcategoryRequest $request,
        CommoditySubcategory $commoditySubcategory,
        CommoditySubcategoryService $service
    ): JsonResponse {
        try {
            $updatedSubcategory = $service->updateCommoditySubcategory(
                $commoditySubcategory,
                $request->validated(),
                $request->user()?->id
            );

            return response()->json([
                'status' => true,
                'message' => 'Commodity subcategory updated successfully.',
                'data' => new CommoditySubcategoryResource($updatedSubcategory),
            ], 200);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'COMMODITY_SUBCATEGORY_IN_USE',
            ], 409);
        }
    }

    /**
     * Update the active/inactive status of a commodity subcategory.
     */
    public function updateStatus(
        UpdateCommoditySubcategoryStatusRequest $request,
        CommoditySubcategory $commoditySubcategory,
        CommoditySubcategoryService $service
    ): JsonResponse {
        $updatedSubcategory = $service->updateStatus(
            $commoditySubcategory,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategory status updated successfully.',
            'data' => new CommoditySubcategoryResource($updatedSubcategory),
        ], 200);
    }

    /**
     * Bulk update status for multiple commodity subcategories.
     */
    public function bulkStatus(
        BulkUpdateCommoditySubcategoryStatusRequest $request,
        CommoditySubcategoryService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategories status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single commodity subcategory safely.
     */
    public function destroy(
        CommoditySubcategory $commoditySubcategory,
        CommoditySubcategoryService $service
    ): JsonResponse {
        try {
            $service->deleteCommoditySubcategory($commoditySubcategory);

            return response()->json([
                'status' => true,
                'message' => 'Commodity subcategory deleted successfully.',
            ], 200);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'COMMODITY_SUBCATEGORY_IN_USE',
            ], 409);
        }
    }

    /**
     * Bulk soft delete multiple commodity subcategories atomically in a single transaction.
     */
    public function bulkDestroy(
        BulkDeleteCommoditySubcategoryRequest $request,
        CommoditySubcategoryService $service
    ): JsonResponse {
        $result = $service->bulkDeleteCommoditySubcategories($request->validated('ids'));

        if (! empty($result['blocked_ids'])) {
            return response()->json([
                'status' => false,
                'message' => 'Some commodity subcategories cannot be deleted because they have varieties assigned.',
                'data' => [
                    'blocked_ids' => $result['blocked_ids'],
                ],
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategories deleted successfully.',
            'data' => [
                'deleted_count' => $result['deleted_count'],
            ],
        ], 200);
    }
}
