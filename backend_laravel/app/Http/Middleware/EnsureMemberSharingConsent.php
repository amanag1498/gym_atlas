<?php

namespace App\Http\Middleware;

use App\Models\IndependentTrainerMemberRelationship;
use App\Models\User;
use App\Services\Privacy\ConsentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberSharingConsent
{
    public function __construct(private readonly ConsentService $consents) {}

    public function handle(Request $request, Closure $next, ?string $additionalPurpose = null): Response
    {
        $member = $request->route('member');
        if ($member !== null) {
            $member = $member instanceof User ? $member : User::query()->findOrFail($member);
        } else {
            $relationship = $request->route('relationship');
            $relationship = $relationship instanceof IndependentTrainerMemberRelationship
                ? $relationship
                : IndependentTrainerMemberRelationship::query()->findOrFail($relationship);
            $member = $relationship->member()->firstOrFail();
        }

        $this->consents->assertGranted($member, 'trainer_member_sharing');
        if ($additionalPurpose !== null) {
            $this->consents->assertGranted($member, $additionalPurpose);
        }

        return $next($request);
    }
}
