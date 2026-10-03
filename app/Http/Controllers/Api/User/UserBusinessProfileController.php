<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessProfileResource;
use App\Jobs\SendUserNotificationJob;
use App\Models\BusinessProfile;
use App\Models\User;
use App\Services\UserActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserBusinessProfileController extends Controller
{
    /**
     * View current authenticated trader's company profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $businessProfile = $user->businessProfile;

        if (! $businessProfile) {
            return response()->json([
                'status' => false,
                'message' => 'No company associated with this user account.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Company details retrieved successfully.',
            'data' => new BusinessProfileResource($businessProfile),
        ], 200);
    }

    /**
     * Store/register company details for authenticated trader after login.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->businessProfile) {
            return response()->json([
                'status' => false,
                'message' => 'User already has an associated company profile.',
                'data' => new BusinessProfileResource($user->businessProfile),
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required_without:company_name', 'nullable', 'string', 'max:255'],
            'company_name' => ['required_without:name', 'nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'business_commodities' => ['nullable'],
            'trade_preference' => ['nullable', 'string', 'in:buy,sell,both,BUY,SELL,BOTH'],
            'buy_sell_preference' => ['nullable', 'string', 'in:buy,sell,both,BUY,SELL,BOTH'],
        ]);

        $companyName = trim((string) ($validated['company_name'] ?? $validated['name']));

        $commodities = $validated['business_commodities'] ?? [];
        if (is_string($commodities)) {
            $decoded = json_decode($commodities, true);
            $commodities = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $commodities)));
        }

        $businessProfile = BusinessProfile::create([
            'name' => $companyName,
            'contact_person' => isset($validated['contact_person']) ? trim((string) $validated['contact_person']) : $user->full_name,
            'business_type' => isset($validated['business_type']) ? trim((string) $validated['business_type']) : null,
            'gstin' => isset($validated['gstin']) ? strtoupper(trim((string) $validated['gstin'])) : null,
            'country' => isset($validated['country']) ? trim((string) $validated['country']) : 'India',
            'state' => isset($validated['state']) ? trim((string) $validated['state']) : null,
            'city' => isset($validated['city']) ? trim((string) $validated['city']) : null,
            'address' => isset($validated['address']) ? trim((string) $validated['address']) : null,
            'business_commodities' => $commodities,
            'trade_preference' => strtolower((string) ($validated['trade_preference'] ?? $validated['buy_sell_preference'] ?? 'both')),
            'verification_status' => 'pending',
        ]);

        $user->businessProfile()->attach($businessProfile->id, [
            'role' => 'trader',
            'is_primary' => true,
        ]);

        UserActivityService::log(
            $user,
            'company_create',
            'Trader registered company profile',
            ['business_profile_id' => $businessProfile->id, 'company_name' => $businessProfile->company_name]
        );

        // In-App Notification: Company Profile Created
        SendUserNotificationJob::dispatch(
            $user->id,
            'Company Profile Created',
            'Hello {{user_first_name}}, your company profile has been created successfully.',
            NotificationType::IN_APP
        );

        return response()->json([
            'status' => true,
            'message' => 'Company profile created successfully.',
            'data' => new BusinessProfileResource($businessProfile->fresh()),
        ], 201);
    }

    /**
     * Update or create current authenticated trader's company profile.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $businessProfile = $user->businessProfile;

        if (! $businessProfile) {
            // Allow update endpoint to create company if none exists yet
            return $this->store($request);
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
            'business_commodities' => ['nullable'],
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

        if (array_key_exists('business_commodities', $validated)) {
            $commodities = $validated['business_commodities'];
            if (is_string($commodities)) {
                $decoded = json_decode($commodities, true);
                $commodities = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $commodities)));
            }
            $updateData['business_commodities'] = $commodities;
        }

        $businessProfile->update($updateData);

        UserActivityService::log(
            $user,
            'company_update',
            'Trader updated company details',
            array_keys($updateData)
        );

        // In-App Notification: Company Profile Updated
        SendUserNotificationJob::dispatch(
            $user->id,
            'Company Profile Updated',
            'Hello {{user_first_name}}, your company details have been updated successfully.',
            NotificationType::IN_APP
        );

        return response()->json([
            'status' => true,
            'message' => 'Company profile updated successfully.',
            'data' => new BusinessProfileResource($businessProfile->fresh()),
        ], 200);
    }
}
