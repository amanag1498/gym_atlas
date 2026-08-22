<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainerEmailInvitation;
use App\Services\Members\TrainerEmailInvitationService;
use Illuminate\Http\Request;

class TrainerGymInvitationController extends Controller
{
    public function respond(Request $request, TrainerEmailInvitation $invitation, TrainerEmailInvitationService $service)
    {
        $data = $request->validate(['decision' => ['required', 'in:accept,reject']]);
        $invite = $service->respondForUser($request->user(), $invitation, $data['decision'] === 'accept');

        return $this->success(['invitation_id' => $invite->id, 'status' => $invite->status], 'Trainer invitation '.$invite->status.'.');
    }
}
