<?php

namespace App\Services\Trainer;

use App\Models\IndependentTrainerMemberRelationship;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class IndependentCoachingAccessService
{
    public function isVerifiedIndependentTrainer(User $trainer): bool
    {
        return $trainer->is_active && TrainerProfile::query()
            ->where('user_id', $trainer->id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->exists();
    }

    public function activeRelationshipsForTrainer(User $trainer): Builder
    {
        $this->assertVerifiedIndependentTrainer($trainer);

        return IndependentTrainerMemberRelationship::query()
            ->where('trainer_user_id', $trainer->id)
            ->where('status', 'active')
            ->whereNotNull('member_user_id');
    }

    public function activeRelationshipsForMember(User $member): Builder
    {
        return IndependentTrainerMemberRelationship::query()
            ->where('member_user_id', $member->id)
            ->where('status', 'active')
            ->whereHas('trainer', fn (Builder $trainer) => $trainer->where('is_active', true))
            ->whereHas('trainer.managedTrainerProfile', function (Builder $query): void {
                $query->where('is_active', true)
                    ->where('status', 'active')
                    ->where('verification_status', 'verified');
            });
    }

    public function resolveActiveRelationship(
        User $trainer,
        User $member,
        ?int $relationshipId = null,
        ?string $capability = null,
    ): IndependentTrainerMemberRelationship {
        $relationship = $this->activeRelationshipsForTrainer($trainer)
            ->where('member_user_id', $member->id)
            ->when($relationshipId !== null, fn (Builder $query) => $query->whereKey($relationshipId))
            ->with(['trainer.managedTrainerProfile', 'member'])
            ->first();

        if (! $relationship) {
            throw ValidationException::withMessages([
                'independent_trainer_member_relationship_id' => [
                    'An active independent coaching relationship is required for this member.',
                ],
            ]);
        }

        if ($capability !== null && ! in_array($capability, $relationship->sharing_permissions ?? [], true)) {
            throw ValidationException::withMessages([
                'independent_trainer_member_relationship_id' => [
                    'The member has not shared '.$capability.' access for this coaching relationship.',
                ],
            ]);
        }

        return $relationship;
    }

    public function resolveForMember(
        User $member,
        int $relationshipId,
        ?string $capability = null,
    ): IndependentTrainerMemberRelationship {
        $relationship = $this->activeRelationshipsForMember($member)
            ->whereKey($relationshipId)
            ->with(['trainer.managedTrainerProfile', 'member'])
            ->first();

        if (! $relationship) {
            throw ValidationException::withMessages([
                'independent_trainer_member_relationship_id' => [
                    'This independent coaching relationship is not active for your account.',
                ],
            ]);
        }

        if ($capability !== null && ! in_array($capability, $relationship->sharing_permissions ?? [], true)) {
            throw ValidationException::withMessages([
                'independent_trainer_member_relationship_id' => [
                    'This coaching relationship does not include '.$capability.' access.',
                ],
            ]);
        }

        return $relationship;
    }

    public function hasActiveRelationship(User $trainer, User $member, ?string $capability = null): bool
    {
        try {
            $this->resolveActiveRelationship($trainer, $member, null, $capability);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /** @return Collection<int, int> */
    public function activeRelationshipIdsForMember(User $member, ?string $capability = null): Collection
    {
        return $this->activeRelationshipsForMember($member)
            ->get(['id', 'sharing_permissions'])
            ->when(
                $capability !== null,
                fn (Collection $relationships) => $relationships->filter(
                    fn (IndependentTrainerMemberRelationship $relationship): bool => in_array(
                        $capability,
                        $relationship->sharing_permissions ?? [],
                        true,
                    ),
                ),
            )
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
    }

    /** @return Collection<int, int> */
    public function activeTrainerIdsForMember(User $member, ?string $capability = null): Collection
    {
        return $this->activeRelationshipsForMember($member)
            ->get(['trainer_user_id', 'sharing_permissions'])
            ->when(
                $capability !== null,
                fn (Collection $relationships) => $relationships->filter(
                    fn (IndependentTrainerMemberRelationship $relationship): bool => in_array(
                        $capability,
                        $relationship->sharing_permissions ?? [],
                        true,
                    ),
                ),
            )
            ->pluck('trainer_user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /** @return Collection<int, int> */
    public function activeMemberIdsForTrainer(User $trainer, ?string $capability = null): Collection
    {
        return $this->activeRelationshipsForTrainer($trainer)
            ->get(['member_user_id', 'sharing_permissions'])
            ->when(
                $capability !== null,
                fn (Collection $relationships) => $relationships->filter(
                    fn (IndependentTrainerMemberRelationship $relationship): bool => in_array(
                        $capability,
                        $relationship->sharing_permissions ?? [],
                        true,
                    ),
                ),
            )
            ->pluck('member_user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    public function assertVerifiedIndependentTrainer(User $trainer): TrainerProfile
    {
        $profile = TrainerProfile::query()
            ->where('user_id', $trainer->id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'trainer' => [
                    'Personal coaching requires an active, verified trainer account.',
                ],
            ]);
        }

        return $profile;
    }
}
