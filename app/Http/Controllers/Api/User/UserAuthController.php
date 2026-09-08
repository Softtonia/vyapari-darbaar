<?php

namespace App\Http\Controllers\Api\User;

use App\Actions\User\ChangeUserPasswordAction;
use App\Actions\User\ForgotPasswordUserAction;
use App\Actions\User\LoginUserAction;
use App\Actions\User\LogoutUserAction;
use App\Actions\User\ResetPasswordUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangeUserPasswordRequest;
use App\Http\Requests\User\ForgotPasswordUserRequest;
use App\Http\Requests\User\LoginUserRequest;
use App\Http\Requests\User\ResetPasswordUserRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAuthController extends Controller
{
    /**
     * Authenticate a user by username and issue a Sanctum token.
     */
    public function login(LoginUserRequest $request, LoginUserAction $action): JsonResponse
    {
        $deviceName = (string) $request->input('device_name', $request->header('User-Agent', 'user-device'));
        $result = $action->execute($request->credentials(), $deviceName);

        if (! $result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => new UserProfileResource($result['user']),
            ],
        ], 200);
    }

    /**
     * Send a password reset link to the given user email address.
     */
    public function forgotPassword(
        ForgotPasswordUserRequest $request,
        ForgotPasswordUserAction $action
    ): JsonResponse {
        $result = $action->execute($request->email());

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
        ], $result['code']);
    }

    /**
     * Reset user password using the provided token.
     */
    public function resetPassword(
        ResetPasswordUserRequest $request,
        ResetPasswordUserAction $action
    ): JsonResponse {
        $result = $action->execute($request->credentials());

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ], $result['code']);
    }

    /**
     * Change the authenticated user's password and revoke other active sessions.
     */
    public function changePassword(
        ChangeUserPasswordRequest $request,
        ChangeUserPasswordAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $action->execute(
            $user,
            (string) $request->input('current_password'),
            (string) $request->input('password')
        );

        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully.',
        ], 200);
    }

    /**
     * Log out the current device session.
     */
    public function logout(Request $request, LogoutUserAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user);

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }
}
