<?php

namespace App\Http\Middleware;

use App\Services\Authorization\TokenRoleContext;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function __construct(
        private readonly TokenRoleContext $tokenRoleContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        if ($user->is_active === false) {
            return ApiResponse::error('This account is inactive. Please contact support.', 403);
        }

        if (! $this->tokenRoleContext->apply($user)) {
            return ApiResponse::error('This session role is no longer available for this account. Please sign in again.', 403);
        }

        return $next($request);
    }
}
