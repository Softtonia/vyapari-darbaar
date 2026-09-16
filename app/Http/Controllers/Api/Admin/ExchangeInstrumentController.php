<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExchangeInstrument\ListExchangeInstrumentRequest;
use App\Http\Requests\Admin\ExchangeInstrument\UpdateExchangeInstrumentEnabledRequest;
use App\Http\Resources\ExchangeInstrumentListResource;
use App\Http\Resources\ExchangeInstrumentOptionResource;
use App\Http\Resources\ExchangeInstrumentResource;
use App\Models\Admin;
use App\Models\ExchangeInstrument;
use App\Services\ExchangeInstrumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeInstrumentController extends Controller
{
    /**
     * Display a paginated listing of exchange instruments.
     */
    public function index(ListExchangeInstrumentRequest $request, ExchangeInstrumentService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchange-instruments.view');

        $paginator = $service->listInstruments($request->validated());

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
     * Get lightweight, cached list of active instruments for a mapping option dropdown.
     */
    public function options(Request $request, ExchangeInstrumentService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchange-instruments.view');

        $mappingId = $request->query('exchange_commodity_mapping_id');
        $mappingId = ($mappingId !== null && $mappingId !== '') ? (int) $mappingId : null;

        $options = $service->getOptions($mappingId);

        return response()->json([
            'status' => true,
            'message' => 'Exchange instrument options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Display the specified exchange instrument detail.
     */
    public function show(Request $request, ExchangeInstrument $instrument): JsonResponse
    {
        $this->authorizeAdmin($request, 'exchange-instruments.view');

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

    /**
     * Update the local is_enabled visibility state for an exchange instrument.
     */
    public function updateEnabled(
        UpdateExchangeInstrumentEnabledRequest $request,
        ExchangeInstrument $instrument,
        ExchangeInstrumentService $service
    ): JsonResponse {
        $this->authorizeAdmin($request, 'exchange-instruments.update');

        $updatedInstrument = $service->setEnabled(
            $instrument,
            $request->validated('is_enabled')
        );

        return response()->json([
            'status' => true,
            'message' => 'Exchange instrument enabled status updated successfully.',
            'data' => new ExchangeInstrumentResource($updatedInstrument),
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
