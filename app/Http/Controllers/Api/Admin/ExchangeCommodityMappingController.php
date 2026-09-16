<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExchangeCommodityMapping\ListExchangeCommodityMappingRequest;
use App\Http\Requests\Admin\ExchangeCommodityMapping\StoreExchangeCommodityMappingRequest;
use App\Http\Requests\Admin\ExchangeCommodityMapping\UpdateExchangeCommodityMappingRequest;
use App\Http\Requests\Admin\ExchangeCommodityMapping\UpdateExchangeCommodityMappingStatusRequest;
use App\Http\Resources\ExchangeCommodityMappingListResource;
use App\Http\Resources\ExchangeCommodityMappingResource;
use App\Models\Admin;
use App\Models\ExchangeCommodityMapping;
use App\Services\ExchangeCommodityMappingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeCommodityMappingController extends Controller
{
    /**
     * Display a paginated listing of commodity mappings.
     */
    public function index(ListExchangeCommodityMappingRequest $request, ExchangeCommodityMappingService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchange-commodity-mappings.view');

        $paginator = $service->listMappings($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Exchange commodity mappings fetched successfully.',
            'data' => [
                'items' => ExchangeCommodityMappingListResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active mappings for dropdown options.
     */
    public function options(Request $request, ExchangeCommodityMappingService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchange-commodity-mappings.view');

        $exchangeId = $request->query('exchange_id');
        $exchangeId = ($exchangeId !== null && $exchangeId !== '') ? (int) $exchangeId : null;

        $commodityId = $request->query('commodity_id');
        $commodityId = ($commodityId !== null && $commodityId !== '') ? (int) $commodityId : null;

        $options = $service->getOptions($exchangeId, $commodityId);

        return response()->json([
            'status' => true,
            'message' => 'Exchange commodity mapping options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created commodity mapping.
     */
    public function store(StoreExchangeCommodityMappingRequest $request, ExchangeCommodityMappingService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchange-commodity-mappings.create');

        $mapping = $service->createMapping(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Exchange commodity mapping created successfully.',
            'data' => new ExchangeCommodityMappingResource($mapping),
        ], 201);
    }

    /**
     * Display the specified commodity mapping detail.
     */
    public function show(Request $request, ExchangeCommodityMapping $mapping): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchange-commodity-mappings.view');

        $mapping->load([
            'exchange:id,name,code,slug',
            'commodity:id,name,code,slug,unit',
            'creator',
            'updater',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Exchange commodity mapping retrieved successfully.',
            'data' => new ExchangeCommodityMappingResource($mapping),
        ], 200);
    }

    /**
     * Update the specified commodity mapping.
     */
    public function update(
        UpdateExchangeCommodityMappingRequest $request,
        ExchangeCommodityMapping $mapping,
        ExchangeCommodityMappingService $service
    ): JsonResponse {
        $this->authorizeAdmin($request, 'exchange-commodity-mappings.update');

        $updatedMapping = $service->updateMapping(
            $mapping,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Exchange commodity mapping updated successfully.',
            'data' => new ExchangeCommodityMappingResource($updatedMapping),
        ], 200);
    }

    /**
     * Update mapping active/inactive status.
     */
    public function updateStatus(
        UpdateExchangeCommodityMappingStatusRequest $request,
        ExchangeCommodityMapping $mapping,
        ExchangeCommodityMappingService $service
    ): JsonResponse {
        $this->authorizeAdmin($request, 'exchange-commodity-mappings.update');

        $updatedMapping = $service->updateStatus(
            $mapping,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Exchange commodity mapping status updated successfully.',
            'data' => new ExchangeCommodityMappingResource($updatedMapping),
        ], 200);
    }

    /**
     * Soft delete a single commodity mapping safely.
     */
    public function destroy(Request $request, ExchangeCommodityMapping $mapping, ExchangeCommodityMappingService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchange-commodity-mappings.delete');

        try {
            $service->deleteMapping($mapping);

            return response()->json([
                'status' => true,
                'message' => 'Exchange commodity mapping deleted successfully.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'EXCHANGE_MAPPING_IN_USE',
            ], 409);
        }
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
