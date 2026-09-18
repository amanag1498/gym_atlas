<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformSettingService;
use Illuminate\Http\Request;

class AppConfigController extends Controller
{
    public function __invoke(Request $request, PlatformSettingService $settings)
    {
        $appType = strtolower(trim((string) ($request->query('app_type') ?: $request->header('X-Atlas-App', 'member'))));
        $platform = strtolower(trim((string) ($request->query('platform') ?: $request->header('X-Client-Platform', 'android'))));
        $currentBuild = (int) ($request->query('build_number') ?: $request->header('X-App-Version-Code', 0));

        return $this->success(
            $settings->publicAppConfig($appType, $platform, $currentBuild),
            'App configuration fetched successfully.',
        );
    }
}
