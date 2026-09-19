<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ListPublicExchangeInstrumentRequest;
use App\Http\Resources\ExchangeInstrumentListResource;
use App\Http\Resources\ExchangeInstrumentResource;
use App\Http\Resources\ExchangeListResource;
use App\Http\Resources\PublicExchangeCommodityResource;
use App\Models\Exchange;
use App\Models\ExchangeCommodityMapping;
use App\Models\ExchangeInstrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicExchangeController extends Controller
{
    /**
     * Display a list of active exchanges.
     */
    public function index(Request $request): JsonResponse
    {
        $exchanges = Exchange::query()
            ->active()
            ->sorted('sort_order', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Exchanges fetched successfully.',
            'data' => ExchangeListResource::collection($exchanges),
        ], 200);
    }

    /**
     * Display business-friendly commodities and products mapped to an active exchange.
     */
    public function commodities(Exchange $exchange): JsonResponse
    {
        if (! $exchange->status) {
            return response()->json([
                'status' => false,
                'message' => 'Exchange is inactive or not found.',
            ], 404);
        }

        $mappings = ExchangeCommodityMapping::query()
            ->where('exchange_id', $exchange->id)
            ->active()
            ->whereHas('commodity', fn ($q) => $q->active())
            ->with(['commodity:id,name,code,slug,unit'])
            ->orderBy('external_symbol', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Exchange commodities fetched successfully.',
            'data' => PublicExchangeCommodityResource::collection($mappings),
        ], 200);
    }

    /**
     * Display active, enabled instruments for an active exchange.
     */
    public function instruments(ListPublicExchangeInstrumentRequest $request, Exchange $exchange): JsonResponse
    {
        if (! $exchange->status) {
            return response()->json([
                'status' => false,
                'message' => 'Exchange is inactive or not found.',
            ], 404);
        }

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'actual_expiry_date');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = ExchangeInstrument::query()
            ->where('exchange_id', $exchange->id)
            ->enabled()
            ->activeLifecycle()
            ->whereHas('mapping', function ($q) {
                $q->active()->whereHas('commodity', fn ($cq) => $cq->active());
            })
            ->with([
                'exchange:id,name,code',
                'mapping:id,exchange_id,commodity_id,external_symbol,external_name',
                'mapping.commodity:id,name,code,unit',
            ])
            ->search($filters['search'] ?? null)
            ->commodity($filters['commodity_id'] ?? null)
            ->instrumentType($filters['instrument_type'] ?? null)
            ->expiryRange($filters['expiry_from'] ?? null, $filters['expiry_to'] ?? null)
            ->sorted($sortBy, $sortOrder);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Exchange instruments fetched successfully.',
            'data' => [
                'items' => ExchangeInstrumentListResource::collection($paginator->items()),
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
     * Display instrument detail.
     */
    public function showInstrument(ExchangeInstrument $instrument): JsonResponse
    {
        // Must be enabled, mapping active, exchange active, commodity active
        if (
            ! $instrument->is_enabled ||
            ! $instrument->mapping?->status ||
            ! $instrument->exchange?->status ||
            ! $instrument->mapping?->commodity?->status
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Exchange instrument is unavailable or inactive.',
            ], 404);
        }

        $instrument->load([
            'exchange:id,name,code,slug',
            'mapping:id,exchange_id,commodity_id,external_symbol,external_code,external_name',
            'mapping.commodity:id,name,code,slug,unit',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Exchange instrument retrieved successfully.',
            'data' => new ExchangeInstrumentResource($instrument),
        ], 200);
    }
}
