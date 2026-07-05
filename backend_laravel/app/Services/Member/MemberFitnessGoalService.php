<?php

namespace App\Services\Member;

use App\Models\FitnessGoal;
use App\Models\MemberProfile;
use Illuminate\Support\Collection;

class MemberFitnessGoalService
{
    /**
     * @return Collection<int, FitnessGoal>
     */
    public function activeOptions(): Collection
    {
        return FitnessGoal::query()->active()->ordered()->get();
    }

    /**
     * @param  list<int>|null  $goalIds
     */
    public function syncForProfile(MemberProfile $profile, ?array $goalIds, ?string $legacySummary = null): void
    {
        if ($goalIds !== null) {
            $resolvedGoals = FitnessGoal::query()
                ->whereIn('id', $goalIds)
                ->ordered()
                ->get();

            $profile->fitnessGoals()->sync($resolvedGoals->pluck('id')->all());
            $profile->forceFill([
                'fitness_goal' => $resolvedGoals->pluck('name')->implode(', ') ?: null,
            ])->save();

            return;
        }

        if ($legacySummary === null) {
            return;
        }

        $matchedIds = $this->resolveIdsFromSummary($legacySummary);

        if ($matchedIds !== []) {
            $this->syncForProfile($profile, $matchedIds, null);
            return;
        }

        $profile->forceFill([
            'fitness_goal' => trim($legacySummary) !== '' ? $legacySummary : null,
        ])->save();
    }

    /**
     * @return list<int>
     */
    public function resolveIdsFromSummary(?string $summary): array
    {
        $tokens = collect(explode(',', (string) $summary))
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->values();

        if ($tokens->isEmpty()) {
            return [];
        }

        $normalized = $tokens
            ->mapWithKeys(fn (string $value): array => [mb_strtolower($value) => true]);

        return FitnessGoal::query()
            ->active()
            ->get(['id', 'name'])
            ->filter(fn (FitnessGoal $goal): bool => $normalized->has(mb_strtolower($goal->name)))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
