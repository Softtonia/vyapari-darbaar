<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommodityGrade\BulkDeleteCommodityGradeRequest;
use App\Http\Requests\Admin\CommodityGrade\BulkUpdateCommodityGradeStatusRequest;
use App\Http\Requests\Admin\CommodityGrade\ListCommodityGradeRequest;
use App\Http\Requests\Admin\CommodityGrade\StoreCommodityGradeRequest;
use App\Http\Requests\Admin\CommodityGrade\UpdateCommodityGradeRequest;
use App\Http\Requests\Admin\CommodityGrade\UpdateCommodityGradeStatusRequest;
use App\Http\Resources\CommodityGradeListResource;
use App\Http\Resources\CommodityGradeResource;
use App\Models\CommodityGrade;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use App\Services\CommodityGradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommodityGradeController extends Controller
{
    /**
     * Display a paginated listing of commodity grades.
     */
    public function index(ListCommodityGradeRequest $request, CommodityGradeService $service): JsonResponse
    {
        $paginator = $service->listCommodityGrades($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Commodity grades fetched successfully.',
            'data' => [
                'items' => CommodityGradeListResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active commodity grades for dropdown options.
     */
    public function options(Request $request, CommodityGradeService $service): JsonResponse
    {
        $commodityId = $request->query('commodity_id');
        $commodityId = ($commodityId !== null && $commodityId !== '') ? (int) $commodityId : null;

        $subcategoryId = $request->query('commodity_subcategory_id');
        $subcategoryId = ($subcategoryId !== null && $subcategoryId !== '') ? (int) $subcategoryId : null;

        $varietyId = $request->query('commodity_variety_id');
        $varietyId = ($varietyId !== null && $varietyId !== '') ? (int) $varietyId : null;

        // Relational consistency validation on query parameters
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

        if ($varietyId !== null && $commodityId !== null) {
            $belongs = CommodityVariety::query()
                ->where('id', $varietyId)
                ->where('commodity_id', $commodityId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $belongs) {
                return response()->json([
                    'status' => false,
                    'message' => 'The selected commodity variety does not belong to the selected commodity.',
                    'errors' => [
                        'commodity_variety_id' => [
                            'The selected commodity variety does not belong to the selected commodity.',
                        ],
                    ],
                ], 422);
            }
        }

        if ($varietyId !== null && $subcategoryId !== null) {
            $belongs = CommodityVariety::query()
                ->where('id', $varietyId)
                ->where('commodity_subcategory_id', $subcategoryId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $belongs) {
                return response()->json([
                    'status' => false,
                    'message' => 'The selected commodity variety does not belong to the selected commodity subcategory.',
                    'errors' => [
                        'commodity_variety_id' => [
                            'The selected commodity variety does not belong to the selected commodity subcategory.',
                        ],
                    ],
                ], 422);
            }
        }

        $options = $service->getOptions($commodityId, $subcategoryId, $varietyId);

        return response()->json([
            'status' => true,
            'message' => 'Commodity grade options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created commodity grade.
     */
    public function store(StoreCommodityGradeRequest $request, CommodityGradeService $service): JsonResponse
    {
        $grade = $service->createCommodityGrade(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity grade created successfully.',
            'data' => new CommodityGradeResource($grade),
        ], 201);
    }

    /**
     * Display the specified commodity grade detail.
     */
    public function show(CommodityGrade $commodityGrade): JsonResponse
    {
        $commodityGrade->load([
            'commodity:id,commodity_category_id,name,slug',
            'commodity.category:id,name,slug',
            'subcategory:id,commodity_id,name,slug',
            'variety:id,commodity_id,commodity_subcategory_id,name,slug',
            'creator',
            'updater',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Commodity grade retrieved successfully.',
            'data' => new CommodityGradeResource($commodityGrade),
        ], 200);
    }

    /**
     * Update the specified commodity grade.
     */
    public function update(
        UpdateCommodityGradeRequest $request,
        CommodityGrade $commodityGrade,
        CommodityGradeService $service
    ): JsonResponse {
        $updatedGrade = $service->updateCommodityGrade(
            $commodityGrade,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity grade updated successfully.',
            'data' => new CommodityGradeResource($updatedGrade),
        ], 200);
    }

    /**
     * Update the active/inactive status of a commodity grade.
     */
    public function updateStatus(
        UpdateCommodityGradeStatusRequest $request,
        CommodityGrade $commodityGrade,
        CommodityGradeService $service
    ): JsonResponse {
        $updatedGrade = $service->updateStatus(
            $commodityGrade,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity grade status updated successfully.',
            'data' => new CommodityGradeResource($updatedGrade),
        ], 200);
    }

    /**
     * Bulk update status for multiple commodity grades.
     */
    public function bulkStatus(
        BulkUpdateCommodityGradeStatusRequest $request,
        CommodityGradeService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity grades status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single commodity grade safely.
     */
    public function destroy(
        CommodityGrade $commodityGrade,
        CommodityGradeService $service
    ): JsonResponse {
        $service->deleteCommodityGrade($commodityGrade);

        return response()->json([
            'status' => true,
            'message' => 'Commodity grade deleted successfully.',
        ], 200);
    }

    /**
     * Bulk soft delete multiple commodity grades atomically in a single transaction.
     */
    public function bulkDestroy(
        BulkDeleteCommodityGradeRequest $request,
        CommodityGradeService $service
    ): JsonResponse {
        $deletedCount = $service->bulkDeleteCommodityGrades($request->validated('ids'));

        return response()->json([
            'status' => true,
            'message' => 'Commodity grades deleted successfully.',
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ], 200);
    }
}
