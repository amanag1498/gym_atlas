<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Audit\PlatformAuditLogResource;
use App\Services\Platform\PlatformAuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly PlatformAuditLogService $platformAuditLogService,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $this->platformAuditLogService->parseFilters($request);
        $paginator = $this->platformAuditLogService->query($filters)
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated(
            $paginator,
            PlatformAuditLogResource::collection($paginator->getCollection()),
            'Platform audit logs fetched successfully.',
        );
    }
}
