<?php

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\FitnessGoal;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Authorization\ActiveRoleManager;
use App\Services\Member\MemberFitnessGoalService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagedUserService
{
    public function __construct(
        private readonly ActiveRoleManager $activeRoleManager,
        private readonly NotificationService $notificationService,
        private readonly MemberFitnessGoalService $memberFitnessGoalService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertStaff(?User $user, Gym $gym, array $data): User
    {
        return DB::transaction(function () use ($user, $gym, $data): User {
            $user = $this->persistUser($user, $data);
            $user->removeRole(RoleName::BranchManager->value);
            $user->removeRole(RoleName::GymStaff->value);
            $user->assignRole($data['role']);

            $this->syncGymMembership(
                user: $user,
                gym: $gym,
                branchIds: $data['branch_ids'] ?? [],
                customPermissions: $data['custom_permissions'] ?? null,
            );

            $this->activeRoleManager->ensureValidActiveRole($user);

            return $user->fresh(['gyms', 'branches', 'roles', 'permissions']);
        });
    }

    public function setStaffActive(User $user, Gym $gym, bool $isActive): User
    {
        return DB::transaction(function () use ($user, $gym, $isActive): User {
            $user->update(['is_active' => $isActive]);

            if ($user->gyms()->where('gyms.id', $gym->id)->exists()) {
                $user->gyms()->updateExistingPivot($gym->id, [
                    'status' => $isActive ? 'active' : 'inactive',
                ]);
            }

            $this->activeRoleManager->ensureValidActiveRole($user);

            return $user->fresh(['gyms', 'branches', 'roles', 'permissions']);
        });
    }

    public function setTrainerActive(User $user, Gym $gym, bool $isActive): User
    {
        return DB::transaction(function () use ($user, $gym, $isActive): User {
            $user->update(['is_active' => $isActive]);

            TrainerProfile::query()
                ->where('user_id', $user->id)
                ->where('gym_id', $gym->id)
                ->update([
                    'is_active' => $isActive,
                    'status' => $isActive ? 'active' : 'inactive',
                ]);

            $this->activeRoleManager->ensureValidActiveRole($user);

            return $user->fresh(['gyms', 'branches', 'roles', 'permissions', 'managedTrainerProfile']);
        });
    }

    public function setMemberActive(User $user, Gym $gym, bool $isActive): User
    {
        return DB::transaction(function () use ($user, $gym, $isActive): User {
            $user->update(['is_active' => $isActive]);

            MemberProfile::query()
                ->where('user_id', $user->id)
                ->where('gym_id', $gym->id)
                ->update([
                    'is_active' => $isActive,
                    'status' => $isActive ? 'active' : 'inactive',
                    'membership_status' => $isActive ? 'active' : 'inactive',
                ]);

            $this->activeRoleManager->ensureValidActiveRole($user);

            return $user->fresh(['gyms', 'branches', 'roles', 'permissions', 'memberProfile']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertTrainer(?User $user, Gym $gym, array $data): User
    {
        return DB::transaction(function () use ($user, $gym, $data): User {
            $user = $this->persistUser($user, $data);
            $user->assignRole(RoleName::Trainer->value);

            $branchId = $data['branch_id'] ?? null;
            $this->syncGymMembership($user, $gym, $branchId ? [$branchId] : []);

            TrainerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'gym_id' => $gym->id,
                    'branch_id' => $branchId,
                    'profile_photo_url' => $data['profile_photo_url'] ?? ($data['avatar'] ?? $user->avatar),
                    'bio' => $data['bio'] ?? null,
                    'specialization' => $data['specialization'] ?? (($data['specializations'][0] ?? null)),
                    'specializations' => $data['specializations'] ?? [],
                    'experience_years' => $data['experience_years'] ?? 0,
                    'certifications' => $data['certifications'] ?? [],
                    'status' => Arr::get($data, 'is_active', true) ? 'active' : 'inactive',
                    'languages' => $data['languages'] ?? [],
                    'availability_notes' => $data['availability_notes'] ?? null,
                    'is_active' => Arr::get($data, 'is_active', true),
                    'verification_status' => $data['verification_status'] ?? 'pending',
                ],
            );

            if (! $user->trainer_onboarding_completed) {
                $user->forceFill([
                    'trainer_onboarding_step' => max(1, (int) ($user->trainer_onboarding_step ?? 1)),
                ])->save();
            }

            $this->activeRoleManager->ensureValidActiveRole($user);

            return $user->fresh(['gyms', 'branches', 'roles', 'permissions', 'managedTrainerProfile']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertMember(?User $user, Gym $gym, array $data): User
    {
        return DB::transaction(function () use ($user, $gym, $data): User {
            $previousTrainerId = $user
                ? MemberProfile::query()
                    ->where('user_id', $user->id)
                    ->where('gym_id', $gym->id)
                    ->value('assigned_trainer_user_id')
                : null;
            $user = $this->persistUser($user, $data);
            $user->assignRole(RoleName::Member->value);

            $branchId = $data['branch_id'] ?? null;
            $trainerId = $data['assigned_trainer_user_id'] ?? null;

            if ($trainerId) {
                $validTrainer = TrainerProfile::query()
                    ->where('gym_id', $gym->id)
                    ->where('user_id', $trainerId)
                    ->exists();

                if (! $validTrainer) {
                    throw ValidationException::withMessages([
                        'assigned_trainer_user_id' => ['Assigned trainer must belong to the same gym.'],
                    ]);
                }
            }

            $this->syncGymMembership($user, $gym, $branchId ? [$branchId] : []);

            MemberProfile::query()->updateOrCreate(
                ['user_id' => $user->id, 'gym_id' => $gym->id],
                [
                    'branch_id' => $branchId,
                    'assigned_trainer_user_id' => $trainerId,
                    'assigned_trainer_id' => $trainerId,
                    'fitness_goal' => $this->fitnessGoalSummary($data),
                    'gender' => $data['gender'] ?? null,
                    'height_cm' => $data['height_cm'] ?? null,
                    'weight_kg' => $data['weight_kg'] ?? null,
                    'experience_level' => $data['experience_level'] ?? null,
                    'medical_notes' => $data['medical_notes'] ?? null,
                    'injury_notes' => $data['injury_notes'] ?? null,
                    'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                    'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                    'biometric_identifier' => $data['biometric_identifier'] ?? null,
                    'biometric_enabled' => (bool) ($data['biometric_enabled'] ?? false),
                    'status' => $data['membership_status'] ?? (Arr::get($data, 'is_active', true) ? 'active' : 'inactive'),
                    'membership_status' => $data['membership_status'] ?? 'active',
                    'membership_expires_on' => $data['membership_expires_on'] ?? null,
                    'is_active' => Arr::get($data, 'is_active', true),
                ],
            );

            $memberProfile = MemberProfile::query()
                ->where('user_id', $user->id)
                ->where('gym_id', $gym->id)
                ->firstOrFail();
            $this->memberFitnessGoalService->syncForProfile(
                $memberProfile,
                $this->fitnessGoalIds($data),
                $data['fitness_goal'] ?? null,
            );

            if (! $user->member_onboarding_completed) {
                $user->forceFill([
                    'member_onboarding_step' => max(1, (int) ($user->member_onboarding_step ?? 1)),
                ])->save();
            }

            $this->activeRoleManager->ensureValidActiveRole($user);

            if ($trainerId && $trainerId !== $previousTrainerId) {
                $trainer = User::query()->find($trainerId);

                $this->notificationService->create(
                    user: $user,
                    type: 'trainer_assignment',
                    title: 'Trainer Assigned',
                    body: 'A trainer has been assigned to your member profile.',
                    gymId: $gym->id,
                    branchId: $branchId,
                    data: ['trainer_user_id' => $trainerId],
                );

                if ($trainer) {
                    $this->notificationService->create(
                        user: $trainer,
                        type: 'trainer_assignment',
                        title: 'New Member Assigned',
                        body: 'A member has been assigned to you.',
                        gymId: $gym->id,
                        branchId: $branchId,
                        data: ['member_user_id' => $user->id],
                    );
                }
            }

            return $user->fresh(['gyms', 'branches', 'roles', 'permissions', 'memberProfile.fitnessGoals']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>|null
     */
    private function fitnessGoalIds(array $data): ?array
    {
        if (array_key_exists('fitness_goal_ids', $data)) {
            return collect($data['fitness_goal_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        if (! array_key_exists('fitness_goal', $data)) {
            return null;
        }

        $resolvedIds = $this->memberFitnessGoalService->resolveIdsFromSummary(
            is_string($data['fitness_goal'] ?? null) ? $data['fitness_goal'] : null,
        );

        return $resolvedIds !== [] ? $resolvedIds : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fitnessGoalSummary(array $data): ?string
    {
        $goalIds = $this->fitnessGoalIds($data);

        if ($goalIds !== null) {
            $summary = FitnessGoal::query()
                ->whereIn('id', $goalIds)
                ->ordered()
                ->pluck('name')
                ->implode(', ');

            return $summary !== '' ? $summary : null;
        }

        $summary = $data['fitness_goal'] ?? null;

        return is_string($summary) && trim($summary) !== '' ? trim($summary) : null;
    }

    public function removeStaff(User $user, Gym $gym): User
    {
        return DB::transaction(function () use ($user, $gym): User {
            $this->detachFromGym($user, $gym);
            $this->activeRoleManager->ensureValidActiveRole($user);

            return $user->fresh(['gyms', 'branches', 'roles', 'permissions']);
        });
    }

    public function removeTrainer(User $user, Gym $gym): User
    {
        return DB::transaction(function () use ($user, $gym): User {
            TrainerProfile::query()->where('user_id', $user->id)->where('gym_id', $gym->id)->delete();
            $this->detachFromGym($user, $gym);
            $user->removeRole(RoleName::Trainer->value);
            $this->activeRoleManager->ensureValidActiveRole($user);

            return $user->fresh(['gyms', 'branches', 'roles', 'permissions']);
        });
    }

    public function removeMember(User $user, Gym $gym): User
    {
        return DB::transaction(function () use ($user, $gym): User {
            MemberProfile::query()->where('user_id', $user->id)->where('gym_id', $gym->id)->delete();
            $this->detachFromGym($user, $gym);
            $user->removeRole(RoleName::Member->value);
            $this->activeRoleManager->ensureValidActiveRole($user);

            return $user->fresh(['gyms', 'branches', 'roles', 'permissions']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistUser(?User $user, array $data): User
    {
        $user ??= User::query()->firstWhere('email', $data['email']) ?? new User();

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'avatar' => $data['avatar'] ?? $user->avatar,
            'auth_provider' => $user->google_id ? 'google' : 'gym_invite',
            'is_active' => Arr::get($data, 'is_active', $user->is_active ?? true),
        ];

        if (array_key_exists('phone', $data)) {
            $payload['phone'] = $data['phone'];
        }

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->fill($payload);
        $user->save();

        return $user;
    }

    /**
     * @param  list<int|string>  $branchIds
     * @param  array<string, mixed>|null  $customPermissions
     */
    private function syncGymMembership(User $user, Gym $gym, array $branchIds = [], ?array $customPermissions = null): void
    {
        $encodedPermissions = $customPermissions !== null ? json_encode(array_values($customPermissions), JSON_THROW_ON_ERROR) : null;

        $branchIds = Branch::query()
            ->where('gym_id', $gym->id)
            ->whereIn('id', $branchIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($branchIds) !== count(array_unique($branchIds))) {
            $branchIds = array_values(array_unique($branchIds));
        }

        $gymPivotPayload = [
            'branch_id' => $branchIds[0] ?? null,
            'role_name' => $this->resolveGymRole($user),
            'custom_permissions' => $encodedPermissions,
            'permissions' => $encodedPermissions,
            'status' => $user->is_active ? 'active' : 'inactive',
            'is_primary' => ! $user->gyms()->exists(),
        ];

        if ($user->gyms()->where('gyms.id', $gym->id)->exists()) {
            $user->gyms()->updateExistingPivot($gym->id, $gymPivotPayload);
        } else {
            $user->gyms()->attach($gym->id, $gymPivotPayload);
        }

        $existingGymBranchIds = Branch::query()
            ->where('gym_id', $gym->id)
            ->pluck('id')
            ->all();

        if ($existingGymBranchIds !== []) {
            $user->branches()->detach($existingGymBranchIds);
        }

        if ($branchIds !== []) {
            $payload = [];

            foreach ($branchIds as $branchId) {
                $payload[$branchId] = [
                    'custom_permissions' => $encodedPermissions,
                    'is_primary' => false,
                ];
            }

            $user->branches()->syncWithoutDetaching($payload);
        }
    }

    private function detachFromGym(User $user, Gym $gym): void
    {
        $branchIds = Branch::query()
            ->where('gym_id', $gym->id)
            ->pluck('id')
            ->all();

        if ($branchIds !== []) {
            $user->branches()->detach($branchIds);
        }

        $user->gyms()->detach($gym->id);
    }

    private function resolveGymRole(User $user): ?string
    {
        foreach ([RoleName::GymOwner->value, RoleName::BranchManager->value, RoleName::GymStaff->value, RoleName::Trainer->value, RoleName::Member->value] as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }
}
