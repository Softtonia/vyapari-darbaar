<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Mandi\BulkDeleteMandiRequest;
use App\Http\Requests\Admin\Mandi\BulkUpdateMandiStatusRequest;
use App\Http\Requests\Admin\Mandi\ListMandiRequest;
use App\Http\Requests\Admin\Mandi\StoreMandiRequest;
use App\Http\Requests\Admin\Mandi\UpdateMandiRequest;
use App\Http\Requests\Admin\Mandi\UpdateMandiStatusRequest;
use App\Http\Resources\MandiListResource;
use App\Http\Resources\MandiResource;
use App\Models\Mandi;
use App\Services\MandiService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MandiController extends Controller
{
    /**
     * Display a paginated listing of mandis.
     */
    public function index(ListMandiRequest $request, MandiService $service): JsonResponse
    {
        $paginator = $service->listMandis($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Mandis fetched successfully.',
            'data' => [
                'items' => MandiListResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active mandis for dropdown options.
     */
    public function options(Request $request, MandiService $service): JsonResponse
    {
        $districtId = $request->filled('district_id') ? (int) $request->input('district_id') : null;
        $stateId = $request->filled('state_id') ? (int) $request->input('state_id') : null;

        try {
            $options = $service->getOptions($districtId, $stateId);

            return response()->json([
                'status' => true,
                'message' => 'Mandi options retrieved successfully.',
                'data' => $options,
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    'district_id' => [$e->getMessage()],
                ],
            ], 422);
        }
    }

    /**
     * Store a newly created mandi.
     */
    public function store(StoreMandiRequest $request, MandiService $service): JsonResponse
    {
        $mandi = $service->createMandi(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Mandi created successfully.',
            'data' => new MandiResource($mandi),
        ], 201);
    }

    /**
     * Display the specified mandi detail.
     */
    public function show(Mandi $mandi): JsonResponse
    {
        $mandi->load([
            'district:id,state_id,name,slug',
            'district.state:id,name,slug,code',
            'creator',
            'updater',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Mandi retrieved successfully.',
            'data' => new MandiResource($mandi),
        ], 200);
    }

    /**
     * Update the specified mandi.
     */
    public function update(
        UpdateMandiRequest $request,
        Mandi $mandi,
        MandiService $service
    ): JsonResponse {
        $updatedMandi = $service->updateMandi(
            $mandi,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Mandi updated successfully.',
            'data' => new MandiResource($updatedMandi),
        ], 200);
    }

    /**
     * Update the active/inactive status of a mandi.
     */
    public function updateStatus(
        UpdateMandiStatusRequest $request,
        Mandi $mandi,
        MandiService $service
    ): JsonResponse {
        $updatedMandi = $service->updateStatus(
            $mandi,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Mandi status updated successfully.',
            'data' => new MandiResource($updatedMandi),
        ], 200);
    }

    /**
     * Bulk update status for multiple mandis.
     */
    public function bulkStatus(
        BulkUpdateMandiStatusRequest $request,
        MandiService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Mandis status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single mandi safely.
     */
    public function destroy(Mandi $mandi, MandiService $service): JsonResponse
    {
        $service->deleteMandi($mandi);

        return response()->json([
            'status' => true,
            'message' => 'Mandi deleted successfully.',
        ], 200);
    }

    /**
     * Bulk soft delete multiple mandis atomically.
     */
    public function bulkDestroy(
        BulkDeleteMandiRequest $request,
        MandiService $service
    ): JsonResponse {
        $result = $service->bulkDeleteMandis($request->validated('ids'));

        return response()->json([
            'status' => true,
            'message' => 'Mandis deleted successfully.',
            'data' => [
                'deleted_count' => $result['deleted_count'],
            ],
        ], 200);
    }
}
