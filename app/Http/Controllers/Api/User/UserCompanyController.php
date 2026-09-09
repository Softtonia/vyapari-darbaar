<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\User;
use App\Services\UserActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserCompanyController extends Controller
{
    /**
     * View current authenticated trader's company profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $company = $user->company;

        if (! $company) {
            return response()->json([
                'status' => false,
                'message' => 'No company associated with this user account.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Company details retrieved successfully.',
            'data' => new CompanyResource($company),
        ], 200);
    }

    /**
     * Update current authenticated trader's company profile.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $company = $user->company;

        if (! $company) {
            return response()->json([
                'status' => false,
                'message' => 'No company associated with this user account.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'commodities_handled' => ['nullable'],
            'trade_preference' => ['nullable', 'string', 'in:buy,sell,both,BUY,SELL,BOTH'],
            'buy_sell_preference' => ['nullable', 'string', 'in:buy,sell,both,BUY,SELL,BOTH'],
        ]);

        $updateData = [];

        if (isset($validated['name'])) {
            $updateData['name'] = trim($validated['name']);
        } elseif (isset($validated['company_name'])) {
            $updateData['name'] = trim($validated['company_name']);
        }

        foreach (['contact_person', 'business_type', 'country', 'state', 'city', 'address'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field] !== null ? trim($validated[$field]) : null;
            }
        }

        if (array_key_exists('gstin', $validated)) {
            $updateData['gstin'] = $validated['gstin'] !== null ? strtoupper(trim($validated['gstin'])) : null;
        }

        if (array_key_exists('trade_preference', $validated)) {
            $updateData['trade_preference'] = strtolower((string) $validated['trade_preference']);
        } elseif (array_key_exists('buy_sell_preference', $validated)) {
            $updateData['trade_preference'] = strtolower((string) $validated['buy_sell_preference']);
        }

        if (array_key_exists('commodities_handled', $validated)) {
            $commodities = $validated['commodities_handled'];
            if (is_string($commodities)) {
                $decoded = json_decode($commodities, true);
                $commodities = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $commodities)));
            }
            $updateData['commodities_handled'] = $commodities;
        }

        $company->update($updateData);

        UserActivityService::log(
            $user,
            'company_update',
            'Trader updated company details',
            array_keys($updateData)
        );

        return response()->json([
            'status' => true,
            'message' => 'Company profile updated successfully.',
            'data' => new CompanyResource($company->fresh()),
        ], 200);
    }
}
