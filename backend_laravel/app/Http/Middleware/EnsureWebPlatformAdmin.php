<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Services\Web\WebPanelContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebPlatformAdmin
{
    public function __construct(
        private readonly WebPanelContext $webPanelContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);
        abort_unless($user->hasRole(RoleName::PlatformAdmin->value), 403);

        $this->webPanelContext->activateRole($user, RoleName::PlatformAdmin->value);

        $freshUser = $user->fresh(['roles', 'gyms', 'branches']);
        $request->setUserResolver(static fn () => $freshUser);

        view()->share($this->webPanelContext->buildSharedViewData($request, $freshUser, 'admin'));

        return $next($request);
    }
}
