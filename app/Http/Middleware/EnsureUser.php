<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Strictly verify that authenticated entity is a User instance
        if (! $user instanceof User) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        // Strictly verify that User account is active
        if ($user->status !== 'active') {
            return new JsonResponse([
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
            ], 403);
        }

        return $next($request);
    }
}
