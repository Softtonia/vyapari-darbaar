<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Profile\UpdateAdminProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Http\Resources\AdminProfileResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    /**
     * Display the authenticated administrator profile.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $admin->loadMissing('roles');

        return response()->json([
            'status' => true,
            'message' => 'Admin profile retrieved successfully.',
            'data' => new AdminProfileResource($admin),
        ], 200);
    }

    /**
     * Update the authenticated administrator profile.
     */
    public function update(
        UpdateAdminProfileRequest $request,
        UpdateAdminProfileAction $action
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = $request->user();

        $updatedAdmin = $action->execute($admin, $request->validatedProfileData());

        return response()->json([
            'status' => true,
            'message' => 'Admin profile updated successfully.',
            'data' => new AdminProfileResource($updatedAdmin),
        ], 200);
    }
}
