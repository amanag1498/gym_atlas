<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Member\TrainerConnectionResource;
use App\Services\Member\MemberAppService;
use Illuminate\Http\Request;

class MemberTrainerController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
    ) {
    }

    public function show(Request $request)
    {
        $profile = $this->memberAppService->memberProfileFor($request->user());
        $userState = $this->memberAppService->userStateFor($request->user(), $profile);

        if (! $profile) {
            return $this->success([
                'enabled' => false,
                'user_state' => $userState,
                'assigned_trainer' => null,
                'trainer_chat_placeholder' => [
                    'enabled' => false,
                    'recipient_user_id' => null,
                    'message' => 'Trainer chat is disabled until a gym assigns you a trainer.',
                ],
            ], 'Trainer chat is disabled until a gym assigns you a trainer.');
        }

        return $this->success(
            array_merge(
                TrainerConnectionResource::make($profile)->resolve(),
                [
                    'enabled' => $profile->assigned_trainer_user_id !== null,
                    'user_state' => $userState,
                ],
            ),
            $profile->assigned_trainer_user_id
                ? 'Trainer connection fetched successfully.'
                : 'Trainer chat is disabled until a gym assigns you a trainer.'
        );
    }
}
