<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformDashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private readonly PlatformDashboardService $dashboardService,
    ) {}

    public function __invoke()
    {
        return $this->success(
            $this->dashboardService->build(),
            'Platform dashboard loaded successfully.',
        );
    }
}
