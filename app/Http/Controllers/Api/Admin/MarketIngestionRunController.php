<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarketIngestionRun\ListMarketIngestionRunRequest;
use App\Http\Resources\MarketIngestionRunResource;
use App\Models\Admin;
use App\Models\MarketIngestionRun;
use App\Services\MarketIngestionRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketIngestionRunController extends Controller
{
    /**
     * Display a paginated listing of market ingestion execution logs.
     */
    public function index(ListMarketIngestionRunRequest $request, MarketIngestionRunService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'market-ingestion-runs.view');

        $paginator = $service->getPaginatedRuns(
            $request->validated(),
            $request->validated('per_page', 15)
        );

        return response()->json([
            'status' => true,
            'message' => 'Market ingestion runs fetched successfully.',
            'data' => [
                'items' => MarketIngestionRunResource::collection($paginator->items()),
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
     * Display the specified ingestion execution log detail with error summary.
     */
    public function show(Request $request, MarketIngestionRun $run): JsonResponse
    {
        $this->authorizeAdmin($request, 'market-ingestion-runs.view');

        $run->loadMissing(['exchange:id,name,code,slug']);

        return response()->json([
            'status' => true,
            'message' => 'Market ingestion run details retrieved successfully.',
            'data' => new MarketIngestionRunResource($run),
        ], 200);
    }

    /**
     * Authorize admin permissions with admin/super_admin role fallback.
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
