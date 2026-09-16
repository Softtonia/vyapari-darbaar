<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Exchange\BulkDeleteExchangeRequest;
use App\Http\Requests\Admin\Exchange\BulkUpdateExchangeStatusRequest;
use App\Http\Requests\Admin\Exchange\ListExchangeRequest;
use App\Http\Requests\Admin\Exchange\StoreExchangeRequest;
use App\Http\Requests\Admin\Exchange\UpdateExchangeRequest;
use App\Http\Requests\Admin\Exchange\UpdateExchangeStatusRequest;
use App\Http\Resources\ExchangeListResource;
use App\Http\Resources\ExchangeOptionResource;
use App\Http\Resources\ExchangeResource;
use App\Models\Admin;
use App\Models\Exchange;
use App\Services\ExchangeService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeController extends Controller
{
    /**
     * Display a paginated listing of exchanges.
     */
    public function index(ListExchangeRequest $request, ExchangeService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchanges.view');

        $paginator = $service->listExchanges($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Exchanges fetched successfully.',
            'data' => [
                'items' => ExchangeListResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active exchanges for dropdown options.
     */
    public function options(Request $request, ExchangeService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchanges.view');

        $options = $service->getOptions();

        return response()->json([
            'status' => true,
            'message' => 'Exchange options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created exchange.
     */
    public function store(StoreExchangeRequest $request, ExchangeService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchanges.create');

        $exchange = $service->createExchange(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Exchange created successfully.',
            'data' => new ExchangeResource($exchange),
        ], 201);
    }

    /**
     * Display the specified exchange detail.
     */
    public function show(Request $request, Exchange $exchange): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchanges.view');

        $exchange->load(['creator', 'updater']);

        return response()->json([
            'status' => true,
            'message' => 'Exchange retrieved successfully.',
            'data' => new ExchangeResource($exchange),
        ], 200);
    }

    /**
     * Update the specified exchange.
     */
    public function update(
        UpdateExchangeRequest $request,
        Exchange $exchange,
        ExchangeService $service
    ): JsonResponse {
        $this->authorizeAdmin($request, 'exchanges.update');

        $updatedExchange = $service->updateExchange(
            $exchange,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Exchange updated successfully.',
            'data' => new ExchangeResource($updatedExchange),
        ], 200);
    }

    /**
     * Update exchange active/inactive status.
     */
    public function updateStatus(
        UpdateExchangeStatusRequest $request,
        Exchange $exchange,
        ExchangeService $service
    ): JsonResponse {
        $this->authorizeAdmin($request, 'exchanges.update');

        $updatedExchange = $service->updateStatus(
            $exchange,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Exchange status updated successfully.',
            'data' => new ExchangeResource($updatedExchange),
        ], 200);
    }

    /**
     * Bulk update status for multiple exchanges.
     */
    public function bulkStatus(
        BulkUpdateExchangeStatusRequest $request,
        ExchangeService $service
    ): JsonResponse {
        $this->authorizeAdmin($request, 'exchanges.update');

        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Exchanges status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single exchange safely.
     */
    public function destroy(Request $request, Exchange $exchange, ExchangeService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchanges.delete');

        try {
            $service->deleteExchange($exchange);

            return response()->json([
                'status' => true,
                'message' => 'Exchange deleted successfully.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'EXCHANGE_IN_USE',
            ], 409);
        }
    }

    /**
     * Bulk soft delete multiple exchanges atomically.
     */
    public function bulkDestroy(
        BulkDeleteExchangeRequest $request,
        ExchangeService $service
    ): JsonResponse {
        $this->authorizeAdmin($request, 'exchanges.delete');

        $result = $service->bulkDeleteExchanges($request->validated('ids'));

        if (! empty($result['blocked_ids'])) {
            return response()->json([
                'status' => false,
                'message' => 'Some exchanges cannot be deleted because they are in use.',
                'data' => [
                    'blocked_ids' => $result['blocked_ids'],
                ],
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Exchanges deleted successfully.',
            'data' => [
                'deleted_count' => $result['deleted_count'],
            ],
        ], 200);
    }

    /**
     * Authorize admin permissions with admin role fallback.
     */
    protected function authorizeAdmin(Request $request, string $permission): void
    {
        /** @var Admin|null $admin */
        $admin = $request->user();
        if ($admin && ! $admin->can($permission) && ! $admin->hasRole('admin') && ! $admin->hasRole('super_admin')) {
            abort(response()->json([
                'status' => false,
                'message' => "Unauthorized action. Missing {$permission} permission.",
            ], 403));
        }
    }
}
