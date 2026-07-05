<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly PlatformReportService $reportService,
    ) {
    }

    public function index(Request $request): View
    {
        return $this->renderReport($request, 'overview');
    }

    public function gyms(Request $request): View
    {
        return $this->renderReport($request, 'gyms');
    }

    public function users(Request $request): View
    {
        return $this->renderReport($request, 'users');
    }

    public function payments(Request $request): View
    {
        return $this->renderReport($request, 'payments');
    }

    public function platformBilling(Request $request): View
    {
        return $this->renderReport($request, 'platform-billing');
    }

    public function attendance(Request $request): View
    {
        return $this->renderReport($request, 'attendance');
    }

    public function customFees(Request $request): View
    {
        return $this->renderReport($request, 'custom-fees');
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $dataset = $this->reportService->build($type, $this->reportService->parseFilters($request));

        return response()->streamDownload(function () use ($dataset): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $dataset['export_columns'] ?? $dataset['columns']);
            foreach ($dataset['export_rows'] ?? $dataset['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'platform-'.$dataset['key'].'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function renderReport(Request $request, string $type): View
    {
        $filters = $this->reportService->parseFilters($request);
        $dataset = $this->reportService->build($type, $filters);

        return view('web.admin.reports.index', [
            'pageTitle' => 'Platform Reports',
            'breadcrumbs' => ['Platform', 'Reports'],
            'reportKey' => $dataset['key'],
            'reportTitle' => $dataset['title'],
            'reportDescription' => $dataset['description'],
            'summaryCards' => $dataset['summary_cards'],
            'chartCards' => $dataset['chart_cards'] ?? [],
            'columns' => $dataset['columns'],
            'rows' => $dataset['rows'],
            'emptyState' => $dataset['empty_state'],
            'reportOptions' => $this->reportService->reportOptions(),
            'reportNavigation' => $this->reportService->navigation(),
            'filterOptions' => $this->reportService->filterOptions(),
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
