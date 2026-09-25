<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DistrictOptionResource;
use App\Http\Resources\MandiOptionResource;
use App\Models\District;
use App\Models\Mandi;
use App\Models\State;
use App\Services\DistrictService;
use App\Services\MandiService;
use App\Services\StateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Get active countries.
     */
    public function countries(): JsonResponse
    {
        $countries = \App\Models\Country::where('status', true)->get(['id', 'name', 'code', 'phone_code']);

        return response()->json([
            'status' => true,
            'message' => 'Countries fetched successfully.',
            'data' => $countries,
        ], 200);
    }

    /**
     * Get active states for public location selection.
     */
    public function states(Request $request, StateService $service): JsonResponse
    {
        // Optionally filter by country_id
        $countryId = $request->input('country_id');
        
        $statesQuery = \App\Models\State::where('status', true);
        if ($countryId) {
            $statesQuery->where('country_id', $countryId);
        }
        
        $states = $statesQuery->orderBy('sort_order')->orderBy('name')->get(['id', 'country_id', 'name', 'code', 'slug']);

        return response()->json([
            'status' => true,
            'message' => 'States fetched successfully.',
            'data' => $states,
        ], 200);
    }

    /**
     * Get active cities filtered by state.
     */
    public function cities(Request $request): JsonResponse
    {
        $stateId = $request->input('state_id');
        
        $citiesQuery = \App\Models\City::where('status', true);
        if ($stateId) {
            $citiesQuery->where('state_id', $stateId);
        }
        
        $cities = $citiesQuery->orderBy('name')->get(['id', 'state_id', 'name']);

        return response()->json([
            'status' => true,
            'message' => 'Cities fetched successfully.',
            'data' => $cities,
        ], 200);
    }

    /**
     * Get active districts for public location selection (filtered by active parent state).
     */
    public function districts(Request $request, DistrictService $service): JsonResponse
    {
        $stateId = $request->filled('state_id') ? (int) $request->input('state_id') : null;

        if ($stateId !== null) {
            $stateIsActive = State::query()->where('id', $stateId)->where('status', true)->exists();
            if (! $stateIsActive) {
                return response()->json([
                    'status' => true,
                    'message' => 'Districts fetched successfully.',
                    'data' => [],
                ], 200);
            }

            $options = $service->getOptions($stateId);

            return response()->json([
                'status' => true,
                'message' => 'Districts fetched successfully.',
                'data' => $options,
            ], 200);
        }

        // Return active districts whose parent state is active
        $districts = District::query()
            ->active()
            ->whereHas('state', fn ($q) => $q->where('status', true))
            ->select(['id', 'state_id', 'name', 'slug', 'code'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Districts fetched successfully.',
            'data' => DistrictOptionResource::collection($districts),
        ], 200);
    }

    /**
     * Get active mandis for public location selection (hierarchy validated).
     */
    public function mandis(Request $request, MandiService $service): JsonResponse
    {
        $districtId = $request->filled('district_id') ? (int) $request->input('district_id') : null;
        $stateId = $request->filled('state_id') ? (int) $request->input('state_id') : null;

        if ($districtId !== null && $stateId !== null) {
            $belongsToState = District::query()
                ->where('id', $districtId)
                ->where('state_id', $stateId)
                ->where('status', true)
                ->whereHas('state', fn ($q) => $q->where('status', true))
                ->exists();

            if (! $belongsToState) {
                return response()->json([
                    'status' => false,
                    'message' => 'The specified district does not belong to the selected active state.',
                    'errors' => [
                        'district_id' => ['The specified district does not belong to the selected active state.'],
                    ],
                ], 422);
            }
        }

        if ($districtId !== null) {
            $districtIsActive = District::query()
                ->where('id', $districtId)
                ->where('status', true)
                ->whereHas('state', fn ($q) => $q->where('status', true))
                ->exists();

            if (! $districtIsActive) {
                return response()->json([
                    'status' => true,
                    'message' => 'Mandis fetched successfully.',
                    'data' => [],
                ], 200);
            }

            $options = $service->getOptions($districtId, null);

            return response()->json([
                'status' => true,
                'message' => 'Mandis fetched successfully.',
                'data' => $options,
            ], 200);
        }

        if ($stateId !== null) {
            $stateIsActive = State::query()->where('id', $stateId)->where('status', true)->exists();
            if (! $stateIsActive) {
                return response()->json([
                    'status' => true,
                    'message' => 'Mandis fetched successfully.',
                    'data' => [],
                ], 200);
            }

            // Only return mandis whose district is active AND state is active
            $mandis = Mandi::query()
                ->active()
                ->whereHas('district', function ($q) use ($stateId) {
                    $q->where('status', true)
                        ->where('state_id', $stateId)
                        ->whereHas('state', fn ($sq) => $sq->where('status', true));
                })
                ->select(['id', 'district_id', 'name', 'slug', 'code', 'market_type'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Mandis fetched successfully.',
                'data' => MandiOptionResource::collection($mandis),
            ], 200);
        }

        $mandis = Mandi::query()
            ->active()
            ->whereHas('district', function ($q) {
                $q->where('status', true)
                    ->whereHas('state', fn ($sq) => $sq->where('status', true));
            })
            ->select(['id', 'district_id', 'name', 'slug', 'code', 'market_type'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Mandis fetched successfully.',
            'data' => MandiOptionResource::collection($mandis),
        ], 200);
    }
}
