<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\ForgotPasswordAdminAction;
use App\Actions\Admin\LoginAdminAction;
use App\Actions\Admin\LogoutAdminAction;
use App\Actions\Admin\LogoutAllAdminSessionsAction;
use App\Actions\Admin\ResetPasswordAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ForgotPasswordAdminRequest;
use App\Http\Requests\Admin\LoginAdminRequest;
use App\Http\Requests\Admin\ResetPasswordAdminRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    /**
     * Authenticate an administrator and issue a Sanctum token.
     */
    public function login(LoginAdminRequest $request, LoginAdminAction $action): JsonResponse
    {
        $deviceName = (string) $request->input('device_name', $request->header('User-Agent', 'admin-token'));
        $result = $action->execute($request->credentials(), $deviceName);

        if (! $result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        return response()->json([
            'status' => true,
            'message' => $result['message'],
            'data' => $result['data'],
        ], 200);
    }

    /**
     * Send a password reset link to the administrator.
     */
    public function forgotPassword(ForgotPasswordAdminRequest $request, ForgotPasswordAdminAction $action): JsonResponse
    {
        $result = $action->execute($request->normalizedEmail());

        $response = [
            'status' => $result['status'],
            'message' => $result['message'],
        ];

        if (! empty($result['data'])) {
            $response['data'] = $result['data'];
        }

        return response()->json($response, $result['code'] ?? 200);
    }

    /**
     * Reset the administrator password using a valid reset token.
     */
    public function resetPassword(ResetPasswordAdminRequest $request, ResetPasswordAdminAction $action): JsonResponse
    {
        $result = $action->execute($request->credentials());

        if (! $result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
                'error' => $result['error'] ?? $result['message'],
            ], $result['code']);
        }

        $response = [
            'status' => true,
            'message' => $result['message'],
        ];

        if (! empty($result['data'])) {
            $response['data'] = $result['data'];
        }

        return response()->json($response, 200);
    }

    /**
     * Revoke the current device's personal access token.
     */
    public function logout(Request $request, LogoutAdminAction $action): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $response = $action->execute($admin);

        return response()->json($response, 200);
    }

    /**
     * Revoke all personal access tokens for the authenticated administrator.
     */
    public function logoutAll(Request $request, LogoutAllAdminSessionsAction $action): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $response = $action->execute($admin);

        return response()->json($response, 200);
    }
}
