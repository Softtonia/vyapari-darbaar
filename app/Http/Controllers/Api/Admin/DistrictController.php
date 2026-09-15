<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\District\BulkDeleteDistrictRequest;
use App\Http\Requests\Admin\District\BulkUpdateDistrictStatusRequest;
use App\Http\Requests\Admin\District\ListDistrictRequest;
use App\Http\Requests\Admin\District\StoreDistrictRequest;
use App\Http\Requests\Admin\District\UpdateDistrictRequest;
use App\Http\Requests\Admin\District\UpdateDistrictStatusRequest;
use App\Http\Resources\DistrictListResource;
use App\Http\Resources\DistrictResource;
use App\Models\District;
use App\Services\DistrictService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    /**
     * Display a paginated listing of districts.
     */
    public function index(ListDistrictRequest $request, DistrictService $service): JsonResponse
    {
        $paginator = $service->listDistricts($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Districts fetched successfully.',
            'data' => [
                'items' => DistrictListResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active districts for dropdown options.
     */
    public function options(Request $request, DistrictService $service): JsonResponse
    {
        $stateId = $request->filled('state_id') ? (int) $request->input('state_id') : null;
        $options = $service->getOptions($stateId);

        return response()->json([
            'status' => true,
            'message' => 'District options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created district.
     */
    public function store(StoreDistrictRequest $request, DistrictService $service): JsonResponse
    {
        $district = $service->createDistrict(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'District created successfully.',
            'data' => new DistrictResource($district),
        ], 201);
    }

    /**
     * Display the specified district detail.
     */
    public function show(District $district): JsonResponse
    {
        $district->load(['state:id,name,slug,code', 'creator', 'updater']);

        return response()->json([
            'status' => true,
            'message' => 'District retrieved successfully.',
            'data' => new DistrictResource($district),
        ], 200);
    }

    /**
     * Update the specified district.
     */
    public function update(
        UpdateDistrictRequest $request,
        District $district,
        DistrictService $service
    ): JsonResponse {
        $updatedDistrict = $service->updateDistrict(
            $district,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'District updated successfully.',
            'data' => new DistrictResource($updatedDistrict),
        ], 200);
    }

    /**
     * Update the active/inactive status of a district.
     */
    public function updateStatus(
        UpdateDistrictStatusRequest $request,
        District $district,
        DistrictService $service
    ): JsonResponse {
        $updatedDistrict = $service->updateStatus(
            $district,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'District status updated successfully.',
            'data' => new DistrictResource($updatedDistrict),
        ], 200);
    }

    /**
     * Bulk update status for multiple districts.
     */
    public function bulkStatus(
        BulkUpdateDistrictStatusRequest $request,
        DistrictService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Districts status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single district safely.
     */
    public function destroy(District $district, DistrictService $service): JsonResponse
    {
        try {
            $service->deleteDistrict($district);

            return response()->json([
                'status' => true,
                'message' => 'District deleted successfully.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'DISTRICT_IN_USE',
            ], 409);
        }
    }

    /**
     * Bulk soft delete multiple districts atomically.
     */
    public function bulkDestroy(
        BulkDeleteDistrictRequest $request,
        DistrictService $service
    ): JsonResponse {
        $result = $service->bulkDeleteDistricts($request->validated('ids'));

        if (! empty($result['blocked_ids'])) {
            return response()->json([
                'status' => false,
                'message' => 'Some districts cannot be deleted because mandis are associated with them.',
                'data' => [
                    'blocked_ids' => $result['blocked_ids'],
                ],
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Districts deleted successfully.',
            'data' => [
                'deleted_count' => $result['deleted_count'],
            ],
        ], 200);
    }
}
