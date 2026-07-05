<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformDashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly PlatformDashboardService $dashboardService,
    ) {}

    public function __invoke(): View
    {
        return view('web.admin.dashboard', [
            'pageTitle' => 'Platform Dashboard',
            'breadcrumbs' => ['Platform', 'Dashboard'],
            ...$this->dashboardService->build(),
        ]);
    }
}
