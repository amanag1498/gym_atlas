<?php

namespace App\Http\Middleware;

use App\Services\Authorization\ScopeResolver;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchScope
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

        $scopedBranchId = $request->route('branch')
            ?? $request->route('branch_id')
            ?? $request->header('X-Branch-Id');
        $bodyBranchId = $request->input('branch_id');

        if ($scopedBranchId && $bodyBranchId && (int) $scopedBranchId !== (int) $bodyBranchId) {
            return ApiResponse::error('Requested branch scope does not match the authenticated branch scope.', 403);
        }

        $branchId = $scopedBranchId ?? $bodyBranchId;

        if ($branchId && $request->user() && ! $this->scopeResolver->canAccessBranch($request->user(), $branchId)) {
            return ApiResponse::error('You do not have access to this branch scope.', 403);
        }

        return $next($request);
    }
}
