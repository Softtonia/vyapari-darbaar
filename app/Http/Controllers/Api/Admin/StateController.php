<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\State\BulkDeleteStateRequest;
use App\Http\Requests\Admin\State\BulkUpdateStateStatusRequest;
use App\Http\Requests\Admin\State\ListStateRequest;
use App\Http\Requests\Admin\State\StoreStateRequest;
use App\Http\Requests\Admin\State\UpdateStateRequest;
use App\Http\Requests\Admin\State\UpdateStateStatusRequest;
use App\Http\Resources\StateListResource;
use App\Http\Resources\StateOptionResource;
use App\Http\Resources\StateResource;
use App\Models\State;
use App\Services\StateService;
use DomainException;
use Illuminate\Http\JsonResponse;

class StateController extends Controller
{
    /**
     * Display a paginated listing of states.
     */
    public function index(ListStateRequest $request, StateService $service): JsonResponse
    {
        $paginator = $service->listStates($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'States fetched successfully.',
            'data' => [
                'items' => StateListResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active states for dropdown options.
     */
    public function options(StateService $service): JsonResponse
    {
        $options = $service->getOptions();

        return response()->json([
            'status' => true,
            'message' => 'State options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created state.
     */
    public function store(StoreStateRequest $request, StateService $service): JsonResponse
    {
        $state = $service->createState(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'State created successfully.',
            'data' => new StateResource($state),
        ], 201);
    }

    /**
     * Display the specified state detail.
     */
    public function show(State $state): JsonResponse
    {
        $state->load(['creator', 'updater']);

        return response()->json([
            'status' => true,
            'message' => 'State retrieved successfully.',
            'data' => new StateResource($state),
        ], 200);
    }

    /**
     * Update the specified state.
     */
    public function update(
        UpdateStateRequest $request,
        State $state,
        StateService $service
    ): JsonResponse {
        $updatedState = $service->updateState(
            $state,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'State updated successfully.',
            'data' => new StateResource($updatedState),
        ], 200);
    }

    /**
     * Update the active/inactive status of a state.
     */
    public function updateStatus(
        UpdateStateStatusRequest $request,
        State $state,
        StateService $service
    ): JsonResponse {
        $updatedState = $service->updateStatus(
            $state,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'State status updated successfully.',
            'data' => new StateResource($updatedState),
        ], 200);
    }

    /**
     * Bulk update status for multiple states.
     */
    public function bulkStatus(
        BulkUpdateStateStatusRequest $request,
        StateService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'States status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single state safely.
     */
    public function destroy(State $state, StateService $service): JsonResponse
    {
        try {
            $service->deleteState($state);

            return response()->json([
                'status' => true,
                'message' => 'State deleted successfully.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'STATE_IN_USE',
            ], 409);
        }
    }

    /**
     * Bulk soft delete multiple states atomically.
     */
    public function bulkDestroy(
        BulkDeleteStateRequest $request,
        StateService $service
    ): JsonResponse {
        $result = $service->bulkDeleteStates($request->validated('ids'));

        if (! empty($result['blocked_ids'])) {
            return response()->json([
                'status' => false,
                'message' => 'Some states cannot be deleted because districts are associated with them.',
                'data' => [
                    'blocked_ids' => $result['blocked_ids'],
                ],
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'States deleted successfully.',
            'data' => [
                'deleted_count' => $result['deleted_count'],
            ],
        ], 200);
    }
}
