<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request for administrative routes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|Admin|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
            ], 403);
        }

        // Verify administrative role or permission
        $hasAdminAccess = (bool) $user->is_default
            || $user->hasAnyRole(['super_admin', 'admin', 'editor'])
            || $user->getAllPermissions()->isNotEmpty();

        if (! $hasAdminAccess) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        return $next($request);
    }
}
