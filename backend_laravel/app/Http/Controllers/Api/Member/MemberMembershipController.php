<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Member\MemberMembershipViewResource;
use App\Services\Member\MemberAppService;
use Illuminate\Http\Request;

class MemberMembershipController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
    ) {
    }

    public function show(Request $request)
    {
        $membership = $this->memberAppService->currentMembershipFor($request->user());

        return $this->success(
            $membership ? MemberMembershipViewResource::make($membership) : null,
            $membership ? 'Membership fetched successfully.' : 'No active gym membership is assigned to this account yet.'
        );
    }

    public function leave(Request $request)
    {
        $result = $this->memberAppService->leaveCurrentGym($request->user());

        return $this->success(
            $result,
            'You have left the current gym. Your account is now independent; gym history remains available to the gym for audit.'
        );
    }
}
