<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\UpdateMemberAppProfileRequest;
use App\Http\Resources\Member\MemberAppProfileResource;
use App\Services\Audit\AuditLogService;
use App\Services\Member\MemberAppService;
use App\Services\Member\MemberFitnessGoalService;
use App\Services\Onboarding\OnboardingProgressService;
use Illuminate\Http\Request;

class MemberProfileController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
        private readonly AuditLogService $auditLogService,
        private readonly MemberFitnessGoalService $memberFitnessGoalService,
        private readonly OnboardingProgressService $onboardingProgressService,
    ) {
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $this->memberAppService->memberProfileFor($user);

        return $this->success(MemberAppProfileResource::make($user));
    }

    public function update(UpdateMemberAppProfileRequest $request)
    {
        $user = $request->user();
        $currentProfile = $this->memberAppService->memberProfileFor($user);
        $profileFields = [
            'fitness_goal',
            'gender',
            'height_cm',
            'weight_kg',
            'experience_level',
            'injury_notes',
            'medical_notes',
            'emergency_contact_name',
            'emergency_contact_phone',
        ];
        $oldValues = [
            'user' => $user->only(['name', 'avatar']),
            'member_profile' => $currentProfile?->toArray(),
        ];

        $user->fill($request->safe()->only(['name', 'avatar']));
        $user->save();

        $memberProfilePayload = $request->safe()->only($profileFields);
        $hasProfileFieldInput = collect($profileFields)->contains(
            fn (string $field): bool => $request->exists($field)
        ) || $request->exists('fitness_goal_ids');

        if ($currentProfile !== null || $hasProfileFieldInput) {
            $memberProfile = $currentProfile ?? $user->memberProfile()->create([
                'is_active' => true,
                'membership_status' => 'inactive',
            ]);

            $memberProfile->fill($memberProfilePayload);
            $memberProfile->save();
            $this->memberFitnessGoalService->syncForProfile(
                $memberProfile,
                $request->validated('fitness_goal_ids'),
                $request->validated('fitness_goal'),
            );
        }

        $user = $this->onboardingProgressService->syncMemberProgress(
            $user,
            $request->validated('member_onboarding_step'),
            (bool) $request->validated('member_onboarding_completed', false),
        );

        $freshUser = $user->fresh();
        $this->memberAppService->memberProfileFor($freshUser);

        $this->auditLogService->log(
            event: 'member.profile.updated',
            action: 'update',
            request: $request,
            subject: $freshUser,
            gym: $freshUser->memberProfile?->gym,
            branch: $freshUser->memberProfile?->branch,
            oldValues: $oldValues,
            newValues: [
                'user' => $freshUser->only(['name', 'avatar']),
                'member_profile' => $freshUser->memberProfile?->toArray(),
            ],
        );

        return $this->success(MemberAppProfileResource::make($freshUser), 'Member profile updated successfully.');
    }
}
