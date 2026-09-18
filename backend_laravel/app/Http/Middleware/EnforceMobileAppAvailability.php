<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformSettingService;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMobileAppAvailability
{
    public function __construct(private readonly PlatformSettingService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request) || ! $this->isMobileAppRequest($request)) {
            return $next($request);
        }

        $values = $this->settings->all();
        if ((bool) ($values['maintenance_mode_enabled'] ?? false)) {
            return ApiResponse::error(
                (string) ($values['maintenance_message'] ?? 'Atlas is temporarily unavailable for maintenance.'),
                503,
                [
                    'code' => 'maintenance_mode',
                    'title' => (string) ($values['maintenance_title'] ?? 'A quick tune-up is underway'),
                ],
            );
        }

        if (! (bool) ($values['force_upgrade_enabled'] ?? false)) {
            return $next($request);
        }

        $appType = strtolower(trim((string) $request->header('X-Atlas-App', '')));
        $platform = strtolower(trim((string) $request->header('X-Client-Platform', '')));
        $currentBuild = (int) $request->header('X-App-Version-Code', 0);

        if (! in_array($appType, ['member', 'trainer'], true)
            || ! in_array($platform, ['android', 'ios'], true)) {
            return ApiResponse::error(
                'A current Atlas app is required to continue.',
                426,
                ['code' => 'app_upgrade_required'],
            );
        }

        $config = $this->settings->publicAppConfig($appType, $platform, $currentBuild);
        if ($currentBuild < (int) $config['minimum_build_number']) {
            return ApiResponse::error(
                (string) $config['update_message'],
                426,
                [
                    'code' => 'app_upgrade_required',
                    'app_type' => $appType,
                    'platform' => $platform,
                    'minimum_build_number' => $config['minimum_build_number'],
                    'minimum_version' => $config['minimum_version'],
                    'store_url' => $config['store_url'],
                ],
            );
        }

        return $next($request);
    }

    private function shouldBypass(Request $request): bool
    {
        return $request->is('api/public/app-config')
            || $request->is('api/public/health')
            || $request->is('api/webhooks/*')
            || $request->is('api/integrations/*')
            || $request->is('api/biometric/*')
            || $request->is('api/internal/*')
            || $request->is('api/platform-admin/*')
            || $request->is('api/gym/*');
    }

    private function isMobileAppRequest(Request $request): bool
    {
        if (trim((string) $request->header('X-Atlas-App', '')) !== '') {
            return true;
        }

        return $request->is('api/member/*')
            || $request->is('api/trainer/*')
            || $request->is('api/public/auth/*')
            || $request->is('api/public/me')
            || $request->is('api/notifications*')
            || $request->is('api/notification-preferences')
            || $request->is('api/fcm-tokens')
            || $request->is('api/chat/*');
    }
}
