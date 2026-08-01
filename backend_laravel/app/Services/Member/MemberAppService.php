<?php

namespace App\Services\Member;

use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\DietPlan;
use App\Models\Gym;
use App\Models\MemberDailyStep;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\TrialRequest;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Services\Members\GymMemberAccessService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberAppService
{
    public function __construct(private readonly GymMemberAccessService $gymMemberAccessService) {}

    public function memberProfileForChat(User $user): ?MemberProfile
    {
        $gymId = $this->selectedGymIdFor($user);

        $query = MemberProfile::query()
            ->where('user_id', $user->id)
            ->when($gymId !== null, fn ($query) => $query->where('gym_id', $gymId));

        $this->gymMemberAccessService->scopeAccessibleProfiles($query);

        return $query
            ->orderByRaw("
                case
                    when is_active = 1 and membership_status = 'active' and gym_id is not null and branch_id is not null then 0
                    when is_active = 1 and gym_id is null then 1
                    when is_active = 1 and gym_id is not null and branch_id is not null then 2
                    when is_active = 1 and gym_id is not null then 3
                    else 4
                end
            ")
            ->latest('id')
            ->first();
    }

    public function gymProfileForTrainer(User $member, int $trainerUserId): ?MemberProfile
    {
        $query = MemberProfile::query()
            ->with(['gym', 'branch'])
            ->where('user_id', $member->id)
            ->where('assigned_trainer_user_id', $trainerUserId)
            ->whereHas('assignedTrainer', fn ($trainer) => $trainer
                ->where('is_active', true)
                ->whereHas('managedTrainerProfile', fn ($profile) => $profile
                    ->where('is_active', true)
                    ->where('status', 'active')))
            ->whereNotNull('gym_id');

        $this->gymMemberAccessService->scopeAccessibleProfiles($query);

        return $query->latest('id')->first();
    }

    public function memberProfileFor(User $user): ?MemberProfile
    {
        $membership = $this->currentMembershipFor($user);
        $selectedGymId = $this->selectedGymIdFor($user);

        $query = MemberProfile::query()
            ->with([
                'gym.facilities',
                'branch.facilities',
                'fitnessGoals',
                'assignedTrainer' => fn ($trainer) => $trainer
                    ->where('is_active', true)
                    ->whereHas('managedTrainerProfile', fn ($trainerProfile) => $trainerProfile
                        ->where('is_active', true)
                        ->where('status', 'active')),
                'assignedTrainer.managedTrainerProfile.gym',
                'assignedTrainer.managedTrainerProfile.branch',
                'assignedTrainer.managedTrainerProfile.assignedMembers',
            ])
            ->where('user_id', $user->id)
            ->where(function ($scope): void {
                $scope->whereNull('gym_id')
                    ->orWhere(function ($gymProfile): void {
                        $this->gymMemberAccessService->scopeAccessibleProfiles($gymProfile);
                    });
            });

        if ($selectedGymId !== null || $membership !== null) {
            $query->where('gym_id', $selectedGymId ?? $membership->gym_id);
        }

        $profile = $query
            ->orderByRaw("
                case
                    when is_active = 1 and membership_status = 'active' and gym_id is not null and branch_id is not null then 0
                    when is_active = 1 and gym_id is null then 1
                    when is_active = 1 and gym_id is not null and branch_id is not null then 2
                    when is_active = 1 and gym_id is not null then 3
                    else 4
                end
            ")
            ->latest('id')
            ->first();

        if ($profile !== null) {
            $user->setRelation('memberProfile', $profile);
        }

        return $profile;
    }

    public function currentMembershipFor(User $user): ?MemberMembership
    {
        $selectedGymId = $this->selectedGymIdFor($user);

        return $this->operationalMembershipsQuery($user)
            ->with([
                'gym.facilities',
                'branch.facilities',
                'membershipPlan',
                'member.memberProfiles.assignedTrainer.managedTrainerProfile.gym',
                'member.memberProfiles.assignedTrainer.managedTrainerProfile.branch',
            ])
            ->when($selectedGymId !== null, fn ($query) => $query->where('gym_id', $selectedGymId))
            ->orderByRaw("
                case status
                    when 'active' then 0
                    when 'frozen' then 1
                    when 'expired' then 2
                    when 'cancelled' then 3
                    else 4
                end
            ")
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function selectedGymIdFor(User $user): ?int
    {
        $requestedGymId = filter_var(
            request()->header('X-Gym-Id', request()->query('gym_id')),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($requestedGymId !== false) {
            $profileQuery = MemberProfile::query()
                ->where('user_id', $user->id)
                ->where('gym_id', (int) $requestedGymId);
            if ($this->gymMemberAccessService->scopeAccessibleProfiles($profileQuery)->exists()
                || $this->operationalMembershipsQuery($user)->where('gym_id', (int) $requestedGymId)->exists()) {
                return (int) $requestedGymId;
            }
        }

        $membershipGymId = $this->operationalMembershipsQuery($user)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->value('gym_id');
        if ($membershipGymId !== null) {
            return (int) $membershipGymId;
        }

        $profileQuery = MemberProfile::query()
            ->where('user_id', $user->id)
            ->whereNotNull('gym_id');

        $profileGymId = $this->gymMemberAccessService->scopeAccessibleProfiles($profileQuery)
            ->latest('id')
            ->value('gym_id');

        return $profileGymId !== null ? (int) $profileGymId : null;
    }

    public function assertRequestedGymContextAccessible(User $user): void
    {
        $rawGymId = request()->header('X-Gym-Id', request()->query('gym_id'));
        if ($rawGymId === null || $rawGymId === '') {
            return;
        }

        $requestedGymId = filter_var(
            $rawGymId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );
        $profileQuery = $requestedGymId === false
            ? null
            : MemberProfile::query()
                ->where('user_id', $user->id)
                ->where('gym_id', (int) $requestedGymId);
        $accessible = $profileQuery !== null
            && ($this->gymMemberAccessService->scopeAccessibleProfiles($profileQuery)->exists()
                || $this->operationalMembershipsQuery($user)->where('gym_id', (int) $requestedGymId)->exists());

        if (! $accessible) {
            throw ValidationException::withMessages([
                'gym_id' => ['The selected gym relationship is no longer active. Refresh your gym workspaces before continuing.'],
            ]);
        }
    }

    /** @return Collection<int, MemberProfile> */
    public function activeGymProfilesFor(User $user): Collection
    {
        $profileQuery = MemberProfile::query()
            ->with([
                'gym:id,name,slug,logo_url',
                'branch:id,gym_id,name,slug,address_line,city',
                'assignedTrainer' => fn ($trainer) => $trainer
                    ->select(['id', 'name', 'avatar'])
                    ->where('is_active', true)
                    ->whereHas('managedTrainerProfile', fn ($trainerProfile) => $trainerProfile
                        ->where('is_active', true)
                        ->where('status', 'active')),
                'assignedTrainer.managedTrainerProfile',
            ])
            ->where('user_id', $user->id)
            ->whereNotNull('gym_id');
        $profiles = $this->gymMemberAccessService->scopeAccessibleProfiles($profileQuery)->get();
        $memberships = $this->operationalMembershipsQuery($user)
            ->with(['membershipPlan', 'gym', 'branch'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->unique('gym_id')
            ->keyBy('gym_id');

        $selectedGymId = $this->selectedGymIdFor($user);

        return $profiles
            ->each(function (MemberProfile $profile) use ($memberships, $selectedGymId): void {
                $profile->setRelation('activeMembership', $memberships->get($profile->gym_id));
                $profile->setAttribute('is_selected', (int) $profile->gym_id === $selectedGymId);
            })
            ->sortByDesc(fn (MemberProfile $profile): bool => (bool) $profile->getAttribute('is_selected'))
            ->values();
    }

    private function operationalMembershipsQuery(User $user)
    {
        return MemberMembership::query()
            ->where('member_id', $user->id)
            ->whereHas('gym', fn ($gym) => $gym
                ->where('is_active', true)
                ->where('status', 'active')
                ->where('operational_access_enabled', true))
            ->where(function ($query): void {
                $query->where('status', 'frozen')
                    ->orWhere(function ($active): void {
                        $active->where('status', 'active')
                            ->whereDate('start_date', '<=', today())
                            ->whereDate('expiry_date', '>=', today());
                    });
            });
    }

    public function leaveCurrentGym(User $user): array
    {
        $membership = $this->currentMembershipFor($user);
        $profile = $this->memberProfileFor($user);
        $gymId = $membership?->gym_id ?? $profile?->gym_id;

        if ($gymId === null) {
            return [
                'left_gym_id' => null,
                'left_branch_id' => null,
                'membership_id' => null,
                'status' => 'independent_user',
            ];
        }

        return $this->removeFromGym($user, (int) $gymId);
    }

    public function removeFromGym(User $user, Gym|int $gym): array
    {
        $gymId = $gym instanceof Gym ? $gym->id : $gym;

        return DB::transaction(function () use ($user, $gymId): array {
            $membership = MemberMembership::query()
                ->where('member_id', $user->id)
                ->where('gym_id', $gymId)
                ->whereIn('status', ['active', 'frozen'])
                ->orderByDesc('start_date')
                ->first();
            $profile = MemberProfile::query()
                ->with('fitnessGoals')
                ->where('user_id', $user->id)
                ->where('gym_id', $gymId)
                ->latest('id')
                ->first();

            if ($membership === null && ($profile === null || $profile->gym_id === null)) {
                return [
                    'left_gym_id' => null,
                    'left_branch_id' => null,
                    'membership_id' => null,
                    'status' => 'independent_user',
                ];
            }

            $gymId = $membership?->gym_id ?? $profile?->gym_id;
            $branchId = $membership?->branch_id ?? $profile?->branch_id;
            $fitnessGoalIds = $profile !== null
                ? $profile->fitnessGoals()->pluck('fitness_goals.id')->all()
                : [];

            if ($membership !== null) {
                $membership->forceFill([
                    'status' => 'cancelled',
                    'payment_status' => $membership->payment_status,
                    'updated_at' => now(),
                ])->save();
            }

            if ($profile !== null && $gymId !== null) {
                $profile->forceFill([
                    'status' => 'inactive',
                    'membership_status' => 'cancelled',
                    'membership_expires_on' => null,
                    'is_active' => false,
                    'assigned_trainer_user_id' => null,
                    'assigned_trainer_id' => null,
                    'updated_at' => now(),
                ])->save();
            }

            if ($gymId !== null) {
                WorkoutPlan::query()
                    ->where('member_id', $user->id)
                    ->where('gym_id', $gymId)
                    ->whereNull('independent_trainer_member_relationship_id')
                    ->where('status', 'active')
                    ->update(['status' => 'inactive']);
                DietPlan::query()
                    ->where('member_id', $user->id)
                    ->where('gym_id', $gymId)
                    ->whereNull('independent_trainer_member_relationship_id')
                    ->where('status', 'active')
                    ->update(['status' => 'inactive']);
                WorkoutSession::query()
                    ->where('member_id', $user->id)
                    ->where('gym_id', $gymId)
                    ->where('status', 'active')
                    ->update(['status' => 'cancelled']);
            }

            if ($gymId !== null) {
                $branchIds = Branch::query()
                    ->where('gym_id', $gymId)
                    ->pluck('id')
                    ->all();

                if ($branchIds !== []) {
                    $user->branches()->detach($branchIds);
                }

                $user->gyms()->detach($gymId);
            }

            $hasRemainingGymAccess = $this->hasActiveGymAccess($user);

            if (! $hasRemainingGymAccess) {
                $independentProfile = MemberProfile::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'gym_id' => null,
                    ],
                    [
                        'fitness_goal' => $profile?->fitness_goal,
                        'gender' => $profile?->gender,
                        'height_cm' => $profile?->height_cm,
                        'weight_kg' => $profile?->weight_kg,
                        'experience_level' => $profile?->experience_level,
                        'medical_notes' => $profile?->medical_notes,
                        'injury_notes' => $profile?->injury_notes,
                        'status' => 'active',
                        'membership_status' => 'inactive',
                        'is_active' => true,
                    ],
                );
                $independentProfile->fitnessGoals()->sync($fitnessGoalIds);
            }

            $user->unsetRelation('memberProfile');
            $user->unsetRelation('gyms');
            $user->unsetRelation('branches');

            return [
                'left_gym_id' => $gymId,
                'left_branch_id' => $branchId,
                'membership_id' => $membership?->id,
                'status' => $hasRemainingGymAccess ? 'gym_member' : 'independent_user',
                'remaining_gym_count' => $this->activeGymProfilesFor($user)->count(),
            ];
        });
    }

    /**
     * Revoke operational gym access after the final active/frozen membership ends.
     * Billing and membership rows remain untouched for audit and re-enrollment.
     */
    public function revokeGymAccess(User $user, Gym|int $gym): void
    {
        $gymId = $gym instanceof Gym ? $gym->id : $gym;

        DB::transaction(function () use ($user, $gymId): void {
            if (MemberMembership::query()
                ->where('member_id', $user->id)
                ->where('gym_id', $gymId)
                ->where(function ($query): void {
                    $query->where('status', 'frozen')
                        ->orWhere(function ($active): void {
                            $active->where('status', 'active')
                                ->whereDate('start_date', '<=', today())
                                ->whereDate('expiry_date', '>=', today());
                        });
                })
                ->exists()) {
                return;
            }

            $profile = MemberProfile::query()
                ->where('user_id', $user->id)
                ->where('gym_id', $gymId)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            $fitnessGoalIds = $profile?->fitnessGoals()->pluck('fitness_goals.id')->all() ?? [];

            if ($profile !== null) {
                $profile->forceFill([
                    'assigned_trainer_user_id' => null,
                    'assigned_trainer_id' => null,
                    'status' => 'inactive',
                    'membership_status' => 'cancelled',
                    'membership_expires_on' => null,
                    'is_active' => false,
                ])->save();
            }

            WorkoutPlan::query()
                ->where('member_id', $user->id)
                ->where('gym_id', $gymId)
                ->whereNull('independent_trainer_member_relationship_id')
                ->where('status', 'active')
                ->update(['status' => 'inactive']);
            DietPlan::query()
                ->where('member_id', $user->id)
                ->where('gym_id', $gymId)
                ->whereNull('independent_trainer_member_relationship_id')
                ->where('status', 'active')
                ->update(['status' => 'inactive']);
            WorkoutSession::query()
                ->where('member_id', $user->id)
                ->where('gym_id', $gymId)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            $branchIds = Branch::query()->where('gym_id', $gymId)->pluck('id')->all();
            if ($branchIds !== []) {
                $user->branches()->detach($branchIds);
            }
            $user->gyms()->detach($gymId);

            if (! $this->hasActiveGymAccess($user)) {
                $independentProfile = MemberProfile::query()->updateOrCreate(
                    ['user_id' => $user->id, 'gym_id' => null],
                    [
                        'fitness_goal' => $profile?->fitness_goal,
                        'gender' => $profile?->gender,
                        'height_cm' => $profile?->height_cm,
                        'weight_kg' => $profile?->weight_kg,
                        'experience_level' => $profile?->experience_level,
                        'medical_notes' => $profile?->medical_notes,
                        'injury_notes' => $profile?->injury_notes,
                        'status' => 'active',
                        'membership_status' => 'inactive',
                        'is_active' => true,
                    ],
                );
                $independentProfile->fitnessGoals()->sync($fitnessGoalIds);
            }

            $user->unsetRelation('memberProfile');
            $user->unsetRelation('gyms');
            $user->unsetRelation('branches');
        });
    }

    private function hasActiveGymAccess(User $user): bool
    {
        return MemberProfile::query()
            ->where('user_id', $user->id)
            ->whereNotNull('gym_id')
            ->where('is_active', true)
            ->whereIn('membership_status', ['active', 'frozen'])
            ->where(function ($query): void {
                $query->where('membership_status', 'frozen')
                    ->orWhereNull('membership_expires_on')
                    ->orWhereDate('membership_expires_on', '>=', today());
            })
            ->exists()
            || MemberMembership::query()
                ->where('member_id', $user->id)
                ->where(function ($query): void {
                    $query->where('status', 'frozen')
                        ->orWhere(function ($active): void {
                            $active->where('status', 'active')
                                ->whereDate('start_date', '<=', today())
                                ->whereDate('expiry_date', '>=', today());
                        });
                })
                ->exists();
    }

    public function currentTrialRequestFor(User $user): ?TrialRequest
    {
        return TrialRequest::query()
            ->where('member_id', $user->id)
            ->whereIn('status', ['pending', 'accepted', 'completed'])
            ->latest('id')
            ->first();
    }

    public function hasActiveMembership(?MemberMembership $membership, ?MemberProfile $profile = null): bool
    {
        $gym = $membership?->gym ?? $profile?->gym;
        if ($gym !== null && (! $gym->is_active || $gym->status !== 'active' || ! $gym->operational_access_enabled)) {
            return false;
        }

        if ($membership !== null) {
            if ($membership->status === 'frozen') {
                return true;
            }
            if ($membership->status === 'active'
                && $membership->start_date?->lte(today())
                && $membership->expiry_date?->gte(today())) {
                return true;
            }
        }

        return $profile !== null
            && $profile->is_active
            && in_array($profile->membership_status, ['active', 'frozen'], true)
            && $profile->gym_id !== null
            && $profile->branch_id !== null
            && ($profile->membership_status === 'frozen'
                || $profile->membership_expires_on === null
                || $profile->membership_expires_on->gte(today()));
    }

    public function userStateFor(
        User $user,
        ?MemberProfile $profile = null,
        ?MemberMembership $membership = null,
        ?TrialRequest $trialRequest = null,
    ): string {
        $profile ??= $this->memberProfileFor($user);
        $membership ??= $this->currentMembershipFor($user);
        $trialRequest ??= $this->currentTrialRequestFor($user);

        if ($this->hasActiveMembership($membership, $profile) && $profile?->assigned_trainer_user_id) {
            return 'gym_member_with_trainer';
        }

        if ($this->hasActiveMembership($membership, $profile)) {
            return 'gym_member';
        }

        if ($trialRequest !== null) {
            return 'trial_user';
        }

        return 'independent_user';
    }

    /**
     * @return array<string, mixed>
     */
    public function attendanceStatusFor(User $user, ?MemberProfile $profile = null): array
    {
        $profile ??= $this->memberProfileFor($user);
        $membership = $this->currentMembershipFor($user);
        $enabled = $profile?->gym_id !== null
            && $profile?->branch_id !== null
            && $this->hasActiveMembership($membership, $profile);

        if (! $enabled) {
            return [
                'enabled' => false,
                'biometric_enabled' => (bool) ($profile?->biometric_enabled ?? false),
                'biometric_registered' => filled($profile?->biometric_identifier),
                'checked_in_today' => false,
                'today_check_in_at' => null,
                'last_check_in_at' => null,
                'check_in_method' => null,
                'message' => 'Attendance unlocks after an active gym membership is assigned.',
            ];
        }

        $todayCheckIn = AttendanceLog::query()
            ->where('member_id', $user->id)
            ->when($profile?->gym_id, fn ($query) => $query->where('gym_id', $profile->gym_id))
            ->when($profile?->branch_id, fn ($query) => $query->where('branch_id', $profile->branch_id))
            ->whereDate('checked_in_at', now()->toDateString())
            ->latest('checked_in_at')
            ->first();

        $latestCheckIn = AttendanceLog::query()
            ->where('member_id', $user->id)
            ->when($profile?->gym_id, fn ($query) => $query->where('gym_id', $profile->gym_id))
            ->when($profile?->branch_id, fn ($query) => $query->where('branch_id', $profile->branch_id))
            ->latest('checked_in_at')
            ->first();

        return [
            'enabled' => true,
            'biometric_enabled' => (bool) ($profile?->biometric_enabled ?? false),
            'biometric_registered' => filled($profile?->biometric_identifier),
            'checked_in_today' => $todayCheckIn !== null,
            'today_check_in_at' => $todayCheckIn?->checked_in_at?->toIso8601String(),
            'last_check_in_at' => $latestCheckIn?->checked_in_at?->toIso8601String(),
            'check_in_method' => $todayCheckIn?->check_in_method,
            'message' => $todayCheckIn !== null
                ? 'Attendance is active for your current gym membership.'
                : 'Attendance is active for your current gym membership.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stepSummaryFor(User $user, ?MemberProfile $profile = null): array
    {
        $timezone = $this->stepTimezoneFor($user, $profile);

        return Cache::remember(
            $this->stepSummaryCacheKey($user, $timezone),
            now()->addSeconds(45),
            function () use ($user, $timezone): array {
                $today = now($timezone)->startOfDay();
                $recentStart = $today->copy()->subDays(89)->startOfDay();
                $recentEnd = $today->copy();
                $todayDate = $today->toDateString();

                $todayRow = MemberDailyStep::query()
                    ->where('user_id', $user->id)
                    ->whereDate('step_date', $todayDate)
                    ->first([
                        'step_date',
                        'steps',
                        'goal_steps',
                        'distance_meters',
                        'calories_estimated',
                        'synced_at',
                    ]);

                $recentStepDates = MemberDailyStep::query()
                    ->where('user_id', $user->id)
                    ->where('step_date', '>=', $recentStart->toDateTimeString())
                    ->where('step_date', '<=', $recentEnd->copy()->endOfDay()->toDateTimeString())
                    ->where('steps', '>', 0)
                    ->orderByDesc('step_date')
                    ->pluck('step_date');

                $streakDays = 0;
                $expectedDate = $today->copy();
                foreach ($recentStepDates as $stepDate) {
                    $normalized = Carbon::parse($stepDate)->startOfDay();
                    if ($normalized->toDateString() !== $expectedDate->toDateString()) {
                        break;
                    }

                    $streakDays++;
                    $expectedDate = $expectedDate->copy()->subDay();
                }

                $steps = (int) ($todayRow?->steps ?? 0);
                $goal = max(1, (int) ($todayRow?->goal_steps ?? 10000));

                return [
                    'today' => $steps,
                    'goal' => $goal,
                    'progressPercent' => (int) min(100, round(($steps / $goal) * 100)),
                    'distanceKm' => round(((int) ($todayRow?->distance_meters ?? 0)) / 1000, 2),
                    'calories' => (int) ($todayRow?->calories_estimated ?? 0),
                    'streakDays' => $streakDays,
                    'lastSyncedAt' => $todayRow?->synced_at?->toIso8601String(),
                ];
            }
        );
    }

    public function forgetStepSummaryCacheFor(User $user, ?MemberProfile $profile = null): void
    {
        Cache::forget($this->stepSummaryCacheKey($user, $this->stepTimezoneFor($user, $profile)));
    }

    public function stepTimezoneFor(User $user, ?MemberProfile $profile = null): string
    {
        $profile ??= $user->relationLoaded('memberProfile')
            ? $user->memberProfile
            : $this->memberProfileFor($user);

        return $profile?->gym?->timezone
            ?? $profile?->branch?->timezone
            ?? config('app.timezone', 'UTC');
    }

    private function stepSummaryCacheKey(User $user, string $timezone): string
    {
        return sprintf(
            'member:dashboard:steps:%d:%s',
            $user->id,
            now($timezone)->toDateString(),
        );
    }
}
