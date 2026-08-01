<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\IndependentTrainerMemberInvitation;
use App\Services\Trainer\IndependentTrainerMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndependentTrainerMemberInvitationController extends Controller
{
    public function review(Request $request, IndependentTrainerMemberInvitation $invitation): View
    {
        abort_unless(hash_equals($invitation->token, (string) $request->query('token')), 404);

        return view('independent-trainer-member-invitations.review', [
            'invitation' => $invitation->load(['trainer.managedTrainerProfile', 'relationship']),
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function respond(
        Request $request,
        IndependentTrainerMemberInvitation $invitation,
        IndependentTrainerMemberService $service,
    ): RedirectResponse {
        abort_unless(hash_equals($invitation->token, (string) $request->query('token')), 404);
        $decision = $request->validate(['decision' => ['required', 'in:accept,reject']])['decision'];
        $decision === 'accept' ? $service->accept($invitation) : $service->decline($invitation);

        return back()->with(
            'status',
            $decision === 'accept'
                ? 'Your independent coaching connection is active. Sign in to Atlas with this email to continue.'
                : 'The independent coaching invitation was declined.',
        );
    }
}
