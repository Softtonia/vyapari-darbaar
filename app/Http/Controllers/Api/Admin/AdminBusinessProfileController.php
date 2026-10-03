<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessProfileResource;
use App\Jobs\SendUserNotificationJob;
use App\Models\Admin;
use App\Models\BusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBusinessProfileController extends Controller
{
    /**
     * Display a listing of companies with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request, 'business_profiles.view');

        $query = BusinessProfile::query()->with('users');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('gstin', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%");
            });
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', strtolower((string) $request->input('verification_status')));
        }

        if ($request->filled('state')) {
            $query->where('state', trim((string) $request->input('state')));
        }

        if ($request->filled('city')) {
            $query->where('city', trim((string) $request->input('city')));
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $businessProfiles = $query->latest('id')->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Companies retrieved successfully.',
            'data' => [
                'items' => BusinessProfileResource::collection($businessProfiles->items()),
                'pagination' => [
                    'current_page' => $businessProfiles->currentPage(),
                    'per_page' => $businessProfiles->perPage(),
                    'total' => $businessProfiles->total(),
                    'last_page' => $businessProfiles->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Display the specified company.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request, 'business_profiles.view');

        $businessProfile = BusinessProfile::with('users')->find($id);

        if (! $businessProfile) {
            return response()->json([
                'status' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Company details retrieved successfully.',
            'data' => new BusinessProfileResource($businessProfile),
        ], 200);
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request, 'business_profiles.update');

        $businessProfile = BusinessProfile::find($id);

        if (! $businessProfile) {
            return response()->json([
                'status' => false,
                'message' => 'Company not found.',
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
            'business_commodities' => ['nullable'],
            'trade_preference' => ['nullable', 'string', 'in:buy,sell,both,BUY,SELL,BOTH'],
            'buy_sell_preference' => ['nullable', 'string', 'in:buy,sell,both,BUY,SELL,BOTH'],
            'verification_status' => ['nullable', 'string', 'in:pending,verified,rejected,PENDING,VERIFIED,REJECTED'],
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

        if (array_key_exists('verification_status', $validated)) {
            $updateData['verification_status'] = strtolower((string) $validated['verification_status']);
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

        return response()->json([
            'status' => true,
            'message' => 'Company updated successfully.',
            'data' => new BusinessProfileResource($businessProfile->fresh(['users'])),
        ], 200);
    }

    /**
     * Update verification status of a company.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request, 'business_profiles.update');

        $businessProfile = BusinessProfile::find($id);

        if (! $businessProfile) {
            return response()->json([
                'status' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $validated = $request->validate([
            'verification_status' => ['required', 'string', 'in:pending,verified,rejected,PENDING,VERIFIED,REJECTED'],
        ]);

        $status = strtolower((string) $validated['verification_status']);
        $businessProfile->update(['verification_status' => $status]);

        // Notify associated company users
        foreach ($businessProfile->users as $companyUser) {
            SendUserNotificationJob::dispatch(
                $companyUser->id,
                'Company Status Updated',
                "Hello {{user_first_name}}, your company {$businessProfile->name} status has been updated to '{$status}'.",
                NotificationType::PUSH_AND_IN_APP
            );
        }

        return response()->json([
            'status' => true,
            'message' => "Company verification status updated to '{$status}' successfully.",
            'data' => new BusinessProfileResource($businessProfile->fresh(['users'])),
        ], 200);
    }

    /**
     * Delete a company.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin($request, 'business_profiles.delete');

        $businessProfile = BusinessProfile::find($id);

        if (! $businessProfile) {
            return response()->json([
                'status' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $businessProfile->delete();

        return response()->json([
            'status' => true,
            'message' => 'Company deleted successfully.',
        ], 200);
    }

    /**
     * Authorize admin permissions with admin role fallback.
     */
    protected function authorizeAdmin(Request $request, string $permission): void
    {
        /** @var Admin|null $admin */
        $admin = $request->user();
        if ($admin && ! $admin->can($permission) && ! $admin->hasRole('admin')) {
            abort(response()->json([
                'status' => false,
                'message' => "Unauthorized action. Missing {$permission} permission.",
            ], 403));
        }
    }
}
