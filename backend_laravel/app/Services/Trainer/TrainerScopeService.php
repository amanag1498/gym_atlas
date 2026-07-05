<?php

namespace App\Services\Trainer;

use App\Enums\RoleName;
use App\Models\AttendanceLog;
use App\Models\MemberProfile;
use App\Models\Notification;
use App\Models\TrainerMemberNote;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Authorization\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TrainerScopeService
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    public function resolveTrainerProfile(Request $request): TrainerProfile
    {
        /** @var User $actor */
        $actor = $request->user();
        $trainerUserId = $request->integer('trainer_user_id');

        if ($actor->active_role === RoleName::Trainer->value) {
            $profile = $actor->managedTrainerProfile;

            if (! $profile) {
                throw ValidationException::withMessages([
                    'trainer_user_id' => ['No trainer profile is configured for this account.'],
                ]);
            }

            return $profile->loadMissing(['user', 'gym', 'branch']);
        }

        if (! $trainerUserId) {
            throw ValidationException::withMessages([
                'trainer_user_id' => ['A trainer_user_id is required for inspector access.'],
            ]);
        }

        $profile = TrainerProfile::query()
            ->with(['user', 'gym', 'branch'])
            ->where('user_id', $trainerUserId)
            ->firstOrFail();

        if ($actor->active_role === RoleName::PlatformAdmin->value) {
            return $profile;
        }

        if ($actor->active_role === RoleName::GymOwner->value && $this->scopeResolver->canAccessGym($actor, $profile->gym_id)) {
            return $profile;
        }

        if ($actor->active_role === RoleName::BranchManager->value && $profile->branch_id !== null && $this->scopeResolver->canAccessBranch($actor, $profile->branch_id)) {
            return $profile;
        }

        throw ValidationException::withMessages([
            'trainer_user_id' => ['You do not have access to this trainer profile.'],
        ]);
    }

    public function assignedMembersQuery(TrainerProfile $trainerProfile): Builder
    {
        return MemberProfile::query()
            ->with([
                'user',
                'branch',
                'gym',
                'memberships' => fn ($query) => $query->latest('start_date'),
                'trainerNotes' => fn ($query) => $query->where('trainer_id', $trainerProfile->user_id)->latest('created_at'),
                'attendanceLogs' => fn ($query) => $query->latest('checked_in_at'),
            ])
            ->where('assigned_trainer_user_id', $trainerProfile->user_id)
            ->where('gym_id', $trainerProfile->gym_id)
            ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id));
    }

    public function resolveAssignedMember(TrainerProfile $trainerProfile, User $member): MemberProfile
    {
        $profile = MemberProfile::query()
            ->with([
                'user',
                'branch',
                'gym',
                'memberships' => fn ($query) => $query->latest('start_date'),
                'trainerNotes' => fn ($query) => $query->where('trainer_id', $trainerProfile->user_id)->latest('created_at'),
                'attendanceLogs' => fn ($query) => $query->latest('checked_in_at'),
            ])
            ->where('user_id', $member->id)
            ->where('assigned_trainer_user_id', $trainerProfile->user_id)
            ->where('gym_id', $trainerProfile->gym_id)
            ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id))
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'member_id' => ['You do not have access to this member.'],
            ]);
        }

        return $profile;
    }

    public function resolveTrainerNote(TrainerProfile $trainerProfile, TrainerMemberNote $note): TrainerMemberNote
    {
        if ((int) $note->trainer_id !== (int) $trainerProfile->user_id) {
            throw ValidationException::withMessages([
                'note_id' => ['You do not have access to this trainer note.'],
            ]);
        }

        return $note->load(['member', 'trainer']);
    }

    public function notificationsQuery(TrainerProfile $trainerProfile): Builder
    {
        return Notification::query()
            ->with(['membership', 'branch'])
            ->where('user_id', $trainerProfile->user_id)
            ->where('gym_id', $trainerProfile->gym_id)
            ->when($trainerProfile->branch_id, fn ($query) => $query->where(function ($builder) use ($trainerProfile): void {
                $builder->whereNull('branch_id')->orWhere('branch_id', $trainerProfile->branch_id);
            }));
    }

    public function attendanceQuery(TrainerProfile $trainerProfile, User $member): Builder
    {
        $this->resolveAssignedMember($trainerProfile, $member);

        return AttendanceLog::query()
            ->where('member_id', $member->id)
            ->where('gym_id', $trainerProfile->gym_id)
            ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id))
            ->latest('checked_in_at');
    }
}
