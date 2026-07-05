<?php

namespace App\Http\Controllers\Api\Gym\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\CustomFeeAuditLogResource;
use App\Models\MemberMembership;
use App\Services\Billing\BillingAccessService;
use Illuminate\Http\Request;

class CustomFeeAuditLogController extends Controller
{
    public function __construct(
        private readonly BillingAccessService $billingAccessService,
    ) {
    }

    public function index(MemberMembership $memberMembership, Request $request)
    {
        $this->authorize('view', $memberMembership);
        $this->billingAccessService->assertMembershipAccess($request->user(), $memberMembership);

        $auditLogs = $memberMembership->customFeeAuditLogs()
            ->with('changer')
            ->latest('changed_at')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(
            $auditLogs,
            CustomFeeAuditLogResource::collection($auditLogs->getCollection()),
            'Custom fee audit logs fetched successfully.',
        );
    }
}
