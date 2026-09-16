<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\MarketInstrumentHistoryRequest;
use App\Http\Resources\MarketInstrumentHistoryResource;
use App\Models\ExchangeInstrument;
use App\Services\MarketHistoryService;
use Illuminate\Http\JsonResponse;

class MarketHistoryController extends Controller
{
    /**
     * Retrieve historical EOD market bhavcopy price series for an exchange instrument.
     */
    public function instrumentHistory(
        MarketInstrumentHistoryRequest $request,
        ExchangeInstrument $instrument,
        MarketHistoryService $service
    ): JsonResponse {
        if (
            ! $instrument->is_enabled ||
            ! $instrument->exchange?->status ||
            ! $instrument->mapping?->status ||
            ! $instrument->mapping?->commodity?->status
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Exchange instrument is unavailable or inactive.',
            ], 404);
        }

        $result = $service->getInstrumentHistory($instrument, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Historical market data retrieved successfully.',
            'data' => new MarketInstrumentHistoryResource($result),
        ], 200);
    }
}
