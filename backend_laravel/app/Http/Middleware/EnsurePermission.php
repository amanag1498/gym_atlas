<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $expectedPermissions = array_values(array_filter(array_map('trim', explode('|', $permissions))));

        if (! $user->hasAnyPermission($expectedPermissions)) {
            return ApiResponse::error('You do not have the required permission.', 403);
        }

        return $next($request);
    }
}
