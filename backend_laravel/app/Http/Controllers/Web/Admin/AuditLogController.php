<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformAuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly PlatformAuditLogService $platformAuditLogService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->platformAuditLogService->parseFilters($request);
        $logs = $this->platformAuditLogService->query($filters)
            ->paginate(20)
            ->withQueryString();

        return view('web.admin.audit-logs.index', [
            'pageTitle' => 'Audit Logs',
            'breadcrumbs' => ['Platform', 'Audit Logs'],
            'auditLogs' => $logs,
            'auditItems' => $this->platformAuditLogService->presentLogs($logs->getCollection()),
            'auditSummary' => $this->platformAuditLogService->summarizeLogs($logs->getCollection()),
            'filters' => [
                'actor' => $filters['actor'],
                'action' => $filters['action'],
                'subject_type' => $filters['subject_type'],
                'gym' => $filters['gym_id'],
                'start_date' => $filters['start_date']?->toDateString(),
                'end_date' => $filters['end_date']?->toDateString(),
            ],
            'subjectTypeOptions' => $this->platformAuditLogService->subjectTypeOptions(),
            'gyms' => $this->platformAuditLogService->gymOptions(),
            'sanitizer' => $this->platformAuditLogService,
        ]);
    }
}
