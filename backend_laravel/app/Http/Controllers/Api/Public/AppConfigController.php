<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformSettingService;

class AppConfigController extends Controller
{
    public function __invoke(PlatformSettingService $settings)
    {
        $values = $settings->all();

        return $this->success([
            'demo_login_enabled' => (bool) ($values['demo_login_enabled'] ?? false),
        ], 'App configuration fetched successfully.');
    }
}
