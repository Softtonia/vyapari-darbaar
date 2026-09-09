<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommodityVariety\BulkDeleteCommodityVarietyRequest;
use App\Http\Requests\Admin\CommodityVariety\BulkUpdateCommodityVarietyStatusRequest;
use App\Http\Requests\Admin\CommodityVariety\ListCommodityVarietyRequest;
use App\Http\Requests\Admin\CommodityVariety\StoreCommodityVarietyRequest;
use App\Http\Requests\Admin\CommodityVariety\UpdateCommodityVarietyRequest;
use App\Http\Requests\Admin\CommodityVariety\UpdateCommodityVarietyStatusRequest;
use App\Http\Resources\CommodityVarietyListResource;
use App\Http\Resources\CommodityVarietyResource;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use App\Services\CommodityVarietyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommodityVarietyController extends Controller
{
    /**
     * Display a paginated listing of commodity varieties.
     */
    public function index(ListCommodityVarietyRequest $request, CommodityVarietyService $service): JsonResponse
    {
        $paginator = $service->listCommodityVarieties($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Commodity varieties fetched successfully.',
            'data' => [
                'items' => CommodityVarietyListResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active commodity varieties for dropdown options.
     */
    public function options(Request $request, CommodityVarietyService $service): JsonResponse
    {
        $commodityId = $request->query('commodity_id');
        $commodityId = ($commodityId !== null && $commodityId !== '') ? (int) $commodityId : null;

        $subcategoryId = $request->query('commodity_subcategory_id');
        $subcategoryId = ($subcategoryId !== null && $subcategoryId !== '') ? (int) $subcategoryId : null;

        if ($commodityId !== null && $subcategoryId !== null) {
            $belongs = CommoditySubcategory::query()
                ->where('id', $subcategoryId)
                ->where('commodity_id', $commodityId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $belongs) {
                return response()->json([
                    'status' => false,
                    'message' => 'The selected commodity subcategory does not belong to the selected commodity.',
                    'errors' => [
                        'commodity_subcategory_id' => [
                            'The selected commodity subcategory does not belong to the selected commodity.',
                        ],
                    ],
                ], 422);
            }
        }

        $options = $service->getOptions($commodityId, $subcategoryId);

        return response()->json([
            'status' => true,
            'message' => 'Commodity variety options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created commodity variety.
     */
    public function store(StoreCommodityVarietyRequest $request, CommodityVarietyService $service): JsonResponse
    {
        $variety = $service->createCommodityVariety(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity variety created successfully.',
            'data' => new CommodityVarietyResource($variety),
        ], 201);
    }

    /**
     * Display the specified commodity variety detail.
     */
    public function show(CommodityVariety $commodityVariety): JsonResponse
    {
        $commodityVariety->load([
            'commodity:id,commodity_category_id,name_en,name_hi,slug',
            'commodity.category:id,name_en,name_hi,slug',
            'subcategory:id,commodity_id,name_en,name_hi,slug',
            'creator',
            'updater',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Commodity variety retrieved successfully.',
            'data' => new CommodityVarietyResource($commodityVariety),
        ], 200);
    }

    /**
     * Update the specified commodity variety.
     */
    public function update(
        UpdateCommodityVarietyRequest $request,
        CommodityVariety $commodityVariety,
        CommodityVarietyService $service
    ): JsonResponse {
        $updatedVariety = $service->updateCommodityVariety(
            $commodityVariety,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity variety updated successfully.',
            'data' => new CommodityVarietyResource($updatedVariety),
        ], 200);
    }

    /**
     * Update the active/inactive status of a commodity variety.
     */
    public function updateStatus(
        UpdateCommodityVarietyStatusRequest $request,
        CommodityVariety $commodityVariety,
        CommodityVarietyService $service
    ): JsonResponse {
        $updatedVariety = $service->updateStatus(
            $commodityVariety,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity variety status updated successfully.',
            'data' => new CommodityVarietyResource($updatedVariety),
        ], 200);
    }

    /**
     * Bulk update status for multiple commodity varieties.
     */
    public function bulkStatus(
        BulkUpdateCommodityVarietyStatusRequest $request,
        CommodityVarietyService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity varieties status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single commodity variety safely.
     */
    public function destroy(
        CommodityVariety $commodityVariety,
        CommodityVarietyService $service
    ): JsonResponse {
        $service->deleteCommodityVariety($commodityVariety);

        return response()->json([
            'status' => true,
            'message' => 'Commodity variety deleted successfully.',
        ], 200);
    }

    /**
     * Bulk soft delete multiple commodity varieties atomically in a single transaction.
     */
    public function bulkDestroy(
        BulkDeleteCommodityVarietyRequest $request,
        CommodityVarietyService $service
    ): JsonResponse {
        $deletedCount = $service->bulkDeleteCommodityVarieties($request->validated('ids'));

        return response()->json([
            'status' => true,
            'message' => 'Commodity varieties deleted successfully.',
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ], 200);
    }
}
