<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Commodity\BulkDeleteCommodityRequest;
use App\Http\Requests\Admin\Commodity\BulkUpdateCommodityStatusRequest;
use App\Http\Requests\Admin\Commodity\ListCommodityRequest;
use App\Http\Requests\Admin\Commodity\StoreCommodityRequest;
use App\Http\Requests\Admin\Commodity\UpdateCommodityRequest;
use App\Http\Requests\Admin\Commodity\UpdateCommodityStatusRequest;
use App\Http\Resources\CommodityListResource;
use App\Http\Resources\CommodityOptionResource;
use App\Http\Resources\CommodityResource;
use App\Models\Commodity;
use App\Services\CommodityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommodityController extends Controller
{
    /**
     * Display a paginated listing of commodities.
     */
    public function index(ListCommodityRequest $request, CommodityService $service): JsonResponse
    {
        $paginator = $service->listCommodities($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Commodities fetched successfully.',
            'data' => [
                'items' => CommodityListResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active commodities for dropdown options.
     */
    public function options(Request $request, CommodityService $service): JsonResponse
    {
        $categoryId = $request->query('commodity_category_id');
        $categoryId = ($categoryId !== null && $categoryId !== '') ? (int) $categoryId : null;

        $options = $service->getOptions($categoryId);

        return response()->json([
            'status' => true,
            'message' => 'Commodity options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created commodity.
     */
    public function store(StoreCommodityRequest $request, CommodityService $service): JsonResponse
    {
        $commodity = $service->createCommodity(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity created successfully.',
            'data' => new CommodityResource($commodity),
        ], 201);
    }

    /**
     * Display the specified commodity detail.
     */
    public function show(Commodity $commodity): JsonResponse
    {
        $commodity->load(['category:id,name_en,name_hi,slug', 'creator', 'updater']);

        return response()->json([
            'status' => true,
            'message' => 'Commodity retrieved successfully.',
            'data' => new CommodityResource($commodity),
        ], 200);
    }

    /**
     * Update the specified commodity.
     */
    public function update(
        UpdateCommodityRequest $request,
        Commodity $commodity,
        CommodityService $service
    ): JsonResponse {
        $updatedCommodity = $service->updateCommodity(
            $commodity,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity updated successfully.',
            'data' => new CommodityResource($updatedCommodity),
        ], 200);
    }

    /**
     * Update the active/inactive status of a commodity.
     */
    public function updateStatus(
        UpdateCommodityStatusRequest $request,
        Commodity $commodity,
        CommodityService $service
    ): JsonResponse {
        $updatedCommodity = $service->updateStatus(
            $commodity,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity status updated successfully.',
            'data' => new CommodityResource($updatedCommodity),
        ], 200);
    }

    /**
     * Bulk update status for multiple commodities.
     */
    public function bulkStatus(
        BulkUpdateCommodityStatusRequest $request,
        CommodityService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodities status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single commodity safely.
     */
    public function destroy(Commodity $commodity, CommodityService $service): JsonResponse
    {
        try {
            $service->deleteCommodity($commodity);

            return response()->json([
                'status' => true,
                'message' => 'Commodity deleted successfully.',
            ], 200);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'COMMODITY_IN_USE',
            ], 409);
        }
    }

    /**
     * Bulk soft delete multiple commodities atomically in a single transaction.
     */
    public function bulkDestroy(
        BulkDeleteCommodityRequest $request,
        CommodityService $service
    ): JsonResponse {
        $result = $service->bulkDeleteCommodities($request->validated('ids'));

        if (! empty($result['blocked_ids'])) {
            return response()->json([
                'status' => false,
                'message' => 'Some commodities cannot be deleted because they are in use.',
                'data' => [
                    'blocked_ids' => $result['blocked_ids'],
                ],
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Commodities deleted successfully.',
            'data' => [
                'deleted_count' => $result['deleted_count'],
            ],
        ], 200);
    }
}
