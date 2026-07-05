<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $expectedRoles = array_values(array_filter(array_map('trim', explode('|', $roles))));

        if (! $user->hasAnyRole($expectedRoles)) {
            return ApiResponse::error('You do not have the required role.', 403);
        }

        return $next($request);
    }
}
