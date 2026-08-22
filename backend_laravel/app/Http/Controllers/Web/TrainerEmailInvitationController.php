<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TrainerEmailInvitation;
use App\Services\Members\TrainerEmailInvitationService;
use Illuminate\Http\Request;

class TrainerEmailInvitationController extends Controller
{
    public function review(Request $request, TrainerEmailInvitation $invitation)
    {
        abort_unless(hash_equals($invitation->token, (string) $request->query('token')), 404);

        return view('member-email-invitations.review', ['invitation' => $invitation->load(['gym', 'branch']), 'actionUrl' => $request->fullUrl(), 'trainerInvitation' => true]);
    }

    public function respond(Request $request, TrainerEmailInvitation $invitation, TrainerEmailInvitationService $service)
    {
        abort_unless(hash_equals($invitation->token, (string) $request->query('token')), 404);
        $decision = $request->validate(['decision' => ['required', 'in:accept,reject']])['decision'];
        $service->respond($invitation, $decision === 'accept');

        return back()->with('status', $decision === 'accept' ? 'Your trainer enrollment is confirmed.' : 'Your trainer invitation was declined.');
    }
}
