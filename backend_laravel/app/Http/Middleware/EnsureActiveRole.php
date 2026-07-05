<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        if (! $user->active_role || ! $user->hasRole($user->active_role)) {
            return ApiResponse::error('No valid active role is set for this account.', 403);
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role): array => explode(',', $role))
            ->map(fn (string $role): string => trim($role))
            ->filter()
            ->values()
            ->all();

        if (! in_array($user->active_role, $allowedRoles, true)) {
            return ApiResponse::error('This endpoint is not available for the active role.', 403);
        }

        return $next($request);
    }
}
