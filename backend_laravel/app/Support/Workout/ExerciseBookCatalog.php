<?php

namespace App\Support\Workout;

use App\Models\Exercise;
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
