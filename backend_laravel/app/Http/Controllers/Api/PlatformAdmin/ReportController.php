<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly PlatformReportService $reportService,
    ) {
    }

    public function index(Request $request)
    {
        return $this->respond($request, 'overview');
    }

    public function gyms(Request $request)
    {
        return $this->respond($request, 'gyms');
    }

    public function users(Request $request)
    {
        return $this->respond($request, 'users');
    }

    public function payments(Request $request)
    {
        return $this->respond($request, 'payments');
    }

    public function attendance(Request $request)
    {
        return $this->respond($request, 'attendance');
    }

    public function customFees(Request $request)
    {
        return $this->respond($request, 'custom-fees');
    }

    private function respond(Request $request, string $type)
    {
        $filters = $this->reportService->parseFilters($request);
        $dataset = $this->reportService->build($type, $filters);

        return $this->success([
            'report_key' => $dataset['key'],
            'report_title' => $dataset['title'],
            'report_description' => $dataset['description'],
            'summary_cards' => $dataset['summary_cards'],
            'chart_cards' => $dataset['chart_cards'] ?? [],
            'columns' => $dataset['columns'],
            'rows' => $this->reportService->normalizeRows($dataset['rows']),
            'empty_state' => $dataset['empty_state'],
            'report_options' => $this->reportService->reportOptions(),
            'filters' => [
                'start_date' => $filters['start_date']->toDateString(),
                'end_date' => $filters['end_date']->toDateString(),
                'city' => $filters['city'],
                'gym' => $filters['gym_id'],
                'status' => $filters['status'],
            ],
        ]);
    }
}
