<?php

namespace App\Support\Workout;

use App\Models\Exercise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExerciseBookCatalog
{
    public const BODY_PART_ORDER = [
        'chest',
        'back',
        'shoulders',
        'arms',
        'core',
        'glutes',
        'quads',
        'hamstrings',
        'calves',
        'full_body',
        'conditioning',
        'mobility',
        'other',
    ];

    public static function bodyPartForMuscleGroup(?string $muscleGroup): string
    {
        $normalized = Str::of((string) $muscleGroup)
            ->trim()
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();

        return match (true) {
            $normalized === '' => 'other',
            str_contains($normalized, 'chest') => 'chest',
            str_contains($normalized, 'back'),
            str_contains($normalized, 'lats'),
            str_contains($normalized, 'trap') => 'back',
            str_contains($normalized, 'shoulder'),
            str_contains($normalized, 'delt') => 'shoulders',
            str_contains($normalized, 'bicep'),
            str_contains($normalized, 'tricep'),
            str_contains($normalized, 'forearm'),
            str_contains($normalized, 'arm') => 'arms',
            str_contains($normalized, 'core'),
            str_contains($normalized, 'ab'),
            str_contains($normalized, 'oblique') => 'core',
            str_contains($normalized, 'glute') => 'glutes',
            str_contains($normalized, 'quad') => 'quads',
            str_contains($normalized, 'hamstring') => 'hamstrings',
            str_contains($normalized, 'calf') => 'calves',
            str_contains($normalized, 'conditioning'),
            str_contains($normalized, 'cardio') => 'conditioning',
            str_contains($normalized, 'mobility'),
            str_contains($normalized, 'recovery') => 'mobility',
            str_contains($normalized, 'full body') => 'full_body',
            str_contains($normalized, 'leg'),
            str_contains($normalized, 'lower body') => 'quads',
            default => 'other',
        };
    }

    public static function bodyPartLabel(string $bodyPart): string
    {
        return match ($bodyPart) {
            'full_body' => 'Full Body',
            default => Str::of($bodyPart)->replace('_', ' ')->title()->toString(),
        };
    }

    public static function applyBodyPartFilter(Builder $query, string $bodyPart): Builder
    {
        $bodyPart = self::bodyPartForMuscleGroup($bodyPart);
        $normalizedColumn = "LOWER(REPLACE(REPLACE(COALESCE(muscle_group, ''), '_', ' '), '-', ' '))";
        $patterns = [
            'chest' => ['%chest%'],
            'back' => ['%back%', '%lats%', '%trap%'],
            'shoulders' => ['%shoulder%', '%delt%'],
            'arms' => ['%bicep%', '%tricep%', '%forearm%', '%arm%'],
            'core' => ['%core%', '%ab%', '%oblique%'],
            'glutes' => ['%glute%'],
            'quads' => ['%quad%', '%leg%', '%lower body%'],
            'hamstrings' => ['%hamstring%'],
            'calves' => ['%calf%'],
            'conditioning' => ['%conditioning%', '%cardio%'],
            'mobility' => ['%mobility%', '%recovery%'],
            'full_body' => ['%full body%'],
        ];

        $precedingPatterns = [];
        foreach (self::BODY_PART_ORDER as $candidate) {
            if ($candidate === $bodyPart) {
                break;
            }

            $precedingPatterns = [...$precedingPatterns, ...($patterns[$candidate] ?? [])];
        }

        return $query->where(function (Builder $builder) use ($bodyPart, $normalizedColumn, $patterns, $precedingPatterns): void {
            foreach ($precedingPatterns as $pattern) {
                $builder->whereRaw("{$normalizedColumn} NOT LIKE ?", [$pattern]);
            }

            $requestedPatterns = $patterns[$bodyPart] ?? [];
            if ($requestedPatterns === []) {
                foreach (collect($patterns)->flatten() as $pattern) {
                    $builder->whereRaw("{$normalizedColumn} NOT LIKE ?", [$pattern]);
                }

                return;
            }

            $builder->where(function (Builder $matching) use ($normalizedColumn, $requestedPatterns): void {
                foreach ($requestedPatterns as $index => $pattern) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $matching->{$method}("{$normalizedColumn} LIKE ?", [$pattern]);
                }
            });
        });
    }

    public static function applyBodyPartOrder(Builder $query): Builder
    {
        $normalizedColumn = "LOWER(REPLACE(REPLACE(COALESCE(muscle_group, ''), '_', ' '), '-', ' '))";
        $cases = [
            [1, ['%chest%']],
            [2, ['%back%', '%lats%', '%trap%']],
            [3, ['%shoulder%', '%delt%']],
            [4, ['%bicep%', '%tricep%', '%forearm%', '%arm%']],
            [5, ['%core%', '%ab%', '%oblique%']],
            [6, ['%glute%']],
            [7, ['%quad%']],
            [8, ['%hamstring%']],
            [9, ['%calf%']],
            [10, ['%conditioning%', '%cardio%']],
            [11, ['%mobility%', '%recovery%']],
            [12, ['%full body%']],
            [7, ['%leg%', '%lower body%']],
        ];
        $sql = 'CASE';
        $bindings = [];

        foreach ($cases as [$position, $patterns]) {
            $conditions = [];
            foreach ($patterns as $pattern) {
                $conditions[] = "{$normalizedColumn} LIKE ?";
                $bindings[] = $pattern;
            }
            $sql .= ' WHEN ('.implode(' OR ', $conditions).") THEN {$position}";
        }

        return $query->orderByRaw($sql.' ELSE 13 END', $bindings)->orderBy('name')->orderBy('id');
    }

    public static function exerciseToArray(Exercise $exercise): array
    {
        $bodyPart = self::bodyPartForMuscleGroup($exercise->muscle_group);

        return [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'body_part' => $bodyPart,
            'body_part_label' => self::bodyPartLabel($bodyPart),
            'muscle_group' => $exercise->muscle_group,
            'secondary_muscles' => $exercise->secondary_muscles ?? [],
            'equipment' => $exercise->equipment,
            'difficulty' => $exercise->difficulty,
            'instructions' => $exercise->instructions,
            'image_url' => $exercise->image_url,
            'video_url' => $exercise->video_url,
            'is_global' => $exercise->is_global,
            'status' => $exercise->status,
            'is_active' => $exercise->is_active,
            'created_by_user_id' => $exercise->created_by_user_id,
            'created_at' => $exercise->created_at?->toIso8601String(),
            'updated_at' => $exercise->updated_at?->toIso8601String(),
        ];
    }

    public static function grouped(Collection $exercises): array
    {
        $grouped = $exercises
            ->map(fn (Exercise $exercise) => self::exerciseToArray($exercise))
            ->groupBy('body_part');

        return collect(self::BODY_PART_ORDER)
            ->map(function (string $bodyPart) use ($grouped): ?array {
                $items = collect($grouped->get($bodyPart, []))
                    ->sortBy('name')
                    ->values()
                    ->all();

                if ($items === []) {
                    return null;
                }

                return [
                    'body_part' => $bodyPart,
                    'label' => self::bodyPartLabel($bodyPart),
                    'count' => count($items),
                    'exercises' => $items,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
