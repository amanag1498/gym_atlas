<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MemberEmailInvitation;
use App\Services\Members\MemberEmailInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberEmailInvitationController extends Controller
{
    public function review(Request $request, MemberEmailInvitation $invitation): View
    {
        abort_unless(hash_equals($invitation->token, (string) $request->query('token')), 404);
        return view('member-email-invitations.review', ['invitation' => $invitation->load(['gym', 'branch', 'assignedTrainer']), 'actionUrl' => $request->fullUrl()]);
    }

    public function respond(Request $request, MemberEmailInvitation $invitation, MemberEmailInvitationService $service): RedirectResponse
    {
        abort_unless(hash_equals($invitation->token, (string) $request->query('token')), 404);
        $decision = $request->validate(['decision' => ['required', 'in:accept,reject']])['decision'];
        $decision === 'accept' ? $service->accept($invitation) : $service->reject($invitation);
        return back()->with('status', $decision === 'accept' ? 'Your enrollment is confirmed. You can now use the gym app with this email.' : 'Your enrollment invitation was declined.');
    }
}
