<?php

namespace App\Http\Middleware;

use App\Services\Authorization\ScopeResolver;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGymScope
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole('platform_admin')) {
            return $next($request);
        }

        $scopedGymId = $request->route('gym')
            ?? $request->route('gym_id')
            ?? $request->header('X-Gym-Id');
        $bodyGymId = $request->input('gym_id');

        if ($scopedGymId && $bodyGymId && (int) $scopedGymId !== (int) $bodyGymId) {
            return ApiResponse::error('Requested gym scope does not match the authenticated gym scope.', 403);
        }

        $gymId = $scopedGymId ?? $bodyGymId;

        if ($gymId && $request->user() && ! $this->scopeResolver->canAccessGym($request->user(), $gymId)) {
            return ApiResponse::error('You do not have access to this gym scope.', 403);
        }

        return $next($request);
    }
}
