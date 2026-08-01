<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Services\Web\WebPanelContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebGymPanelAccess
{
    public function __construct(
        private readonly WebPanelContext $webPanelContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 401);
        abort_if($user->is_active === false, 403, 'This account is inactive. Please contact support.');
        abort_if(
            $user->active_role === RoleName::PlatformAdmin->value
                && ! $request->session()->has('web_panel.platform_admin_impersonator_id'),
            403,
            'Open a gym workspace through the platform admin preview action.',
        );
        abort_unless($user->hasAnyRole($this->webPanelContext->allowedGymPanelRoles()), 403);

        if (! $user->active_role || ! in_array($user->active_role, $this->webPanelContext->allowedGymPanelRoles(), true)) {
            foreach ($this->webPanelContext->allowedGymPanelRoles() as $role) {
                if ($user->hasRole($role)) {
                    $this->webPanelContext->activateRole($user, $role);
                    break;
                }
            }
        }

        $freshUser = $user->fresh(['roles', 'gyms', 'branches']);
        $request->setUserResolver(static fn () => $freshUser);

        view()->share($this->webPanelContext->buildSharedViewData($request, $freshUser, 'gym'));

        return $next($request);
    }
}
