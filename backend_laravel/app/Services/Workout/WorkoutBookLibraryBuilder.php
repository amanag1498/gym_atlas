<?php

namespace App\Services\Workout;

use App\Models\Exercise;
use App\Support\Workout\ExerciseBookCatalog;
use Illuminate\Support\Collection;
use RuntimeException;

class WorkoutBookLibraryBuilder
{
    public const BOOK_COUNT = 45;

    /** @param Collection<int, Exercise> $exercises
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $exercises): array
    {
        if ($exercises->isEmpty()) {
            throw new RuntimeException('The approved global exercise catalog is empty.');
        }

        $books = [];
        $slots = [];
        foreach ($this->families() as $family) {
            foreach (['beginner', 'intermediate', 'advanced'] as $level) {
                $bookIndex = count($books);
                $dayIndexes = [];
                foreach ($family['days'] as $day) {
                    $dayIndexes[] = count($slots);
                    $slots[] = [
                        'book_index' => $bookIndex,
                        'label' => $day[0],
                        'parts' => $day[1],
                        'profile' => $family['equipment_profile'],
                        'level' => $level,
                        'exercise_ids' => [],
                    ];
                }
                $books[] = [
                    'name' => ucfirst($level).' '.$family['name'].' Book',
                    'audience' => ucfirst($level).' members',
                    'goal' => $family['goal'],
                    'difficulty' => $level,
                    'program_type' => $family['program_type'],
                    'equipment_profile' => $family['equipment_profile'],
                    'days_per_week' => count($dayIndexes),
                    'duration_weeks' => match ($level) {
                        'beginner' => 4,
                        'intermediate' => 6,
                        default => 8,
                    },
                    'estimated_session_minutes' => $family['minutes'],
                    'description' => $family['description'].' Adapted for '.strtolower($level).' training.',
                    'coach_notes' => 'Select a manageable load, keep technique controlled, and progress only when all sets are comfortable.',
                    'is_featured' => $level === 'beginner' && in_array($family['program_type'], ['full_body', 'upper_lower', 'home_training'], true),
                    'status' => 'active',
                    '_day_indexes' => $dayIndexes,
                ];
            }
        }

        if (count($books) !== self::BOOK_COUNT) {
            throw new RuntimeException('The workout-book definition must contain exactly 45 books.');
        }

        $exerciseById = $exercises->keyBy('id');
        foreach ($exercises->sortBy('id') as $exercise) {
            $slotIndex = $this->bestSlot($slots, $exercise);
            $slots[$slotIndex]['exercise_ids'][] = $exercise->id;
        }

        // Small specialist pools are deliberately reused across books, never duplicated within a day.
        foreach ($slots as $slotIndex => $slot) {
            while (count($slots[$slotIndex]['exercise_ids']) < 5) {
                $candidate = $this->fillCandidate($slots[$slotIndex], $exerciseById, $slots);
                if ($candidate === null) {
                    throw new RuntimeException('Not enough compatible exercises for '.$slot['label'].'.');
                }
                $slots[$slotIndex]['exercise_ids'][] = $candidate;
            }
        }

        $coveredIds = collect($slots)->flatMap(fn (array $slot) => $slot['exercise_ids'])->unique()->sort()->values();
        if ($coveredIds->count() !== $exercises->count()) {
            throw new RuntimeException('Not every approved global exercise was placed in a workout book.');
        }

        foreach ($books as &$book) {
            $dayIndexes = $book['_day_indexes'];
            unset($book['_day_indexes']);
            $days = [];
            foreach ($dayIndexes as $position => $slotIndex) {
                $slot = $slots[$slotIndex];
                $dayExercises = [];
                foreach ($slot['exercise_ids'] as $order => $exerciseId) {
                    $exercise = $exerciseById->get($exerciseId);
                    $part = $this->part($exercise);
                    $dayExercises[] = [
                        'exercise_id' => $exerciseId,
                        'sort_order' => $order + 1,
                        'sets' => $part === 'mobility' ? 2 : ($book['difficulty'] === 'advanced' ? 4 : 3),
                        'reps' => in_array($part, ['conditioning', 'mobility'], true) ? '30-45 sec' : ($book['difficulty'] === 'advanced' ? '6-10' : '8-12'),
                        'rest_seconds' => in_array($part, ['conditioning', 'mobility'], true) ? 45 : ($book['difficulty'] === 'advanced' ? 90 : 75),
                    ];
                }
                $days[] = [
                    'day_number' => $position + 1,
                    'label' => $slot['label'],
                    'focus' => $slot['label'],
                    'exercises' => $dayExercises,
                ];
            }
            $book['plans'] = [[
                'name' => $book['name'].' Plan',
                'goal' => $book['goal'],
                'difficulty' => $book['difficulty'],
                'program_type' => $book['program_type'],
                'equipment_profile' => $book['equipment_profile'],
                'duration_weeks' => $book['duration_weeks'],
                'estimated_session_minutes' => $book['estimated_session_minutes'],
                'weekly_schedule' => array_slice(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], 0, count($days)),
                'notes' => $book['coach_notes'],
                'days' => $days,
            ]];
        }

        return $books;
    }

    /** @param array<int, array<string, mixed>> $slots */
    private function bestSlot(array $slots, Exercise $exercise): int
    {
        $bestIndex = 0;
        $bestScore = PHP_INT_MIN;
        foreach ($slots as $index => $slot) {
            $affinity = $this->affinity($slot, $exercise);
            if ($affinity === 0 || ! $this->levelAllows($slot['level'], $exercise) || ! $this->profileAllows($slot['profile'], $exercise)) {
                continue;
            }
            $score = $affinity * 100 - count($slot['exercise_ids']) * 25;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        if ($bestScore !== PHP_INT_MIN) {
            return $bestIndex;
        }

        // An unusual import taxonomy must still be represented, but only in a general gym day.
        foreach ($slots as $index => $slot) {
            if ($slot['parts'] === ['full_body'] && $slot['profile'] === 'full_gym' && $this->levelAllows($slot['level'], $exercise)) {
                if ($bestScore === PHP_INT_MIN || count($slot['exercise_ids']) < count($slots[$bestIndex]['exercise_ids'])) {
                    $bestScore = 0;
                    $bestIndex = $index;
                }
            }
        }

        return $bestIndex;
    }

    /** @param array<string, mixed> $slot
     * @param  Collection<int, Exercise>  $exercises
     * @param  array<int, array<string, mixed>>  $slots
     */
    private function fillCandidate(array $slot, Collection $exercises, array $slots): ?int
    {
        $usedInBook = collect($slots)
            ->where('book_index', $slot['book_index'])
            ->flatMap(fn (array $candidate) => $candidate['exercise_ids'])
            ->all();
        $usage = collect($slots)->flatMap(fn (array $candidate) => $candidate['exercise_ids'])->countBy();
        $candidates = $exercises->filter(fn (Exercise $exercise) => $this->affinity($slot, $exercise) > 0
            && $this->levelAllows($slot['level'], $exercise)
            && $this->profileAllows($slot['profile'], $exercise)
            && ! in_array($exercise->id, $slot['exercise_ids'], true));
        $candidate = $candidates->reject(fn (Exercise $exercise) => in_array($exercise->id, $usedInBook, true))
            ->sortBy(fn (Exercise $exercise) => ($usage->get($exercise->id, 0) * 100000) + $exercise->id)
            ->first();

        return ($candidate ?? $candidates->first())?->id;
    }

    /** @param array<string, mixed> $slot */
    private function affinity(array $slot, Exercise $exercise): int
    {
        $part = $this->part($exercise);
        if (in_array($part, $slot['parts'], true)) {
            return 4;
        }
        if (in_array('upper', $slot['parts'], true) && in_array($part, ['chest', 'back', 'shoulders', 'arms'], true)) {
            return 3;
        }
        if (in_array('lower', $slot['parts'], true) && in_array($part, ['glutes', 'quads', 'hamstrings', 'calves'], true)) {
            return 3;
        }

        return in_array('full_body', $slot['parts'], true) ? 1 : 0;
    }

    private function part(Exercise $exercise): string
    {
        $part = $exercise->body_part ?: ExerciseBookCatalog::bodyPartForMuscleGroup($exercise->muscle_group);

        return in_array($part, ExerciseBookCatalog::BODY_PART_ORDER, true) ? $part : 'other';
    }

    private function levelAllows(string $level, Exercise $exercise): bool
    {
        $rank = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];

        return ($rank[$level] ?? 3) >= ($rank[strtolower((string) $exercise->difficulty)] ?? 1);
    }

    private function profileAllows(string $profile, Exercise $exercise): bool
    {
        if ($profile === 'full_gym') {
            return true;
        }
        $equipment = strtolower((string) $exercise->equipment);
        $bodyweight = (bool) $exercise->is_bodyweight || str_contains($equipment, 'bodyweight') || $equipment === '' || $equipment === 'none';

        return match ($profile) {
            'bodyweight' => $bodyweight || str_contains($equipment, 'band'),
            'dumbbell' => $bodyweight || str_contains($equipment, 'dumbbell'),
            'minimal_equipment' => $bodyweight || str_contains($equipment, 'band') || str_contains($equipment, 'dumbbell') || str_contains($equipment, 'kettlebell') || str_contains($equipment, 'jump rope'),
            default => true,
        };
    }

    /** @return array<int, array{name: string, goal: string, program_type: string, equipment_profile: string, minutes: int, description: string, days: array<int, array{string, array<int, string>}>}> */
    private function families(): array
    {
        return [
            ['name' => '3-Day Full Body', 'goal' => 'Whole-body strength and consistency', 'program_type' => 'full_body', 'equipment_profile' => 'full_gym', 'minutes' => 55, 'description' => 'Three balanced whole-body sessions.', 'days' => [['Full Body A', ['full_body']], ['Full Body B', ['full_body']], ['Full Body C', ['full_body']]]],
            ['name' => '4-Day Full Body', 'goal' => 'Frequent whole-body practice', 'program_type' => 'full_body', 'equipment_profile' => 'full_gym', 'minutes' => 50, 'description' => 'Four shorter whole-body sessions.', 'days' => [['Full Body A', ['full_body']], ['Full Body B', ['full_body']], ['Full Body C', ['full_body']], ['Full Body D', ['full_body']]]],
            ['name' => '4-Day Upper Lower', 'goal' => 'Balanced strength and muscle', 'program_type' => 'upper_lower', 'equipment_profile' => 'full_gym', 'minutes' => 65, 'description' => 'Alternating upper- and lower-body sessions.', 'days' => [['Upper A', ['upper']], ['Lower A', ['lower']], ['Upper B', ['upper']], ['Lower B', ['lower']]]],
            ['name' => '3-Day Push Pull Legs', 'goal' => 'Classic muscle-group split', 'program_type' => 'push_pull_legs', 'equipment_profile' => 'full_gym', 'minutes' => 65, 'description' => 'One push, one pull, and one leg session each week.', 'days' => [['Push', ['chest', 'shoulders', 'arms']], ['Pull', ['back', 'arms']], ['Legs', ['lower']]]],
            ['name' => '6-Day Push Pull Legs', 'goal' => 'Higher-frequency hypertrophy', 'program_type' => 'push_pull_legs', 'equipment_profile' => 'full_gym', 'minutes' => 60, 'description' => 'Push, pull, and legs repeated twice weekly.', 'days' => [['Push A', ['chest', 'shoulders', 'arms']], ['Pull A', ['back', 'arms']], ['Legs A', ['lower']], ['Push B', ['chest', 'shoulders', 'arms']], ['Pull B', ['back', 'arms']], ['Legs B', ['lower']]]],
            ['name' => '6-Day Arnold Split', 'goal' => 'Bodybuilding volume and focus', 'program_type' => 'arnold_split', 'equipment_profile' => 'full_gym', 'minutes' => 70, 'description' => 'Chest/back, shoulders/arms, and legs repeated.', 'days' => [['Chest and Back A', ['chest', 'back']], ['Shoulders and Arms A', ['shoulders', 'arms']], ['Legs A', ['lower']], ['Chest and Back B', ['chest', 'back']], ['Shoulders and Arms B', ['shoulders', 'arms']], ['Legs B', ['lower']]]],
            ['name' => '5-Day Body Part Split', 'goal' => 'Focused hypertrophy', 'program_type' => 'body_part_split', 'equipment_profile' => 'full_gym', 'minutes' => 65, 'description' => 'A dedicated session for each major region.', 'days' => [['Chest', ['chest']], ['Back', ['back']], ['Legs', ['lower']], ['Shoulders', ['shoulders']], ['Arms', ['arms']]]],
            ['name' => '3-Day Strength', 'goal' => 'Progressive full-body strength', 'program_type' => 'strength', 'equipment_profile' => 'full_gym', 'minutes' => 70, 'description' => 'Three strength-focused whole-body sessions.', 'days' => [['Squat Emphasis', ['quads', 'glutes', 'full_body']], ['Press Emphasis', ['chest', 'shoulders', 'full_body']], ['Hinge Emphasis', ['hamstrings', 'back', 'full_body']]]],
            ['name' => '4-Day Powerbuilding', 'goal' => 'Strength with accessory volume', 'program_type' => 'powerbuilding', 'equipment_profile' => 'full_gym', 'minutes' => 70, 'description' => 'Heavy upper/lower work supported by accessories.', 'days' => [['Upper Strength', ['upper']], ['Lower Strength', ['lower']], ['Upper Volume', ['upper']], ['Lower Volume', ['lower']]]],
            ['name' => '4-Day Glutes and Lower', 'goal' => 'Lower-body and glute emphasis', 'program_type' => 'glute_lower', 'equipment_profile' => 'full_gym', 'minutes' => 60, 'description' => 'Two lower-body priorities with upper-body balance.', 'days' => [['Glutes and Hamstrings', ['glutes', 'hamstrings']], ['Upper A', ['upper']], ['Quads and Calves', ['quads', 'calves']], ['Upper B', ['upper']]]],
            ['name' => '3-Day Home Bodyweight', 'goal' => 'Strength without gym equipment', 'program_type' => 'home_training', 'equipment_profile' => 'bodyweight', 'minutes' => 40, 'description' => 'Accessible home sessions with bodyweight and bands.', 'days' => [['Home A', ['full_body']], ['Home B', ['full_body']], ['Home C', ['full_body']]]],
            ['name' => '4-Day Dumbbell', 'goal' => 'Flexible dumbbell-based training', 'program_type' => 'dumbbell_training', 'equipment_profile' => 'dumbbell', 'minutes' => 50, 'description' => 'Upper/lower training with dumbbells and bodyweight.', 'days' => [['Upper A', ['upper']], ['Lower A', ['lower']], ['Upper B', ['upper']], ['Lower B', ['lower']]]],
            ['name' => '3-Day Conditioning', 'goal' => 'Cardiovascular capacity and work rate', 'program_type' => 'conditioning_circuit', 'equipment_profile' => 'minimal_equipment', 'minutes' => 40, 'description' => 'Conditioning with manageable strength circuits.', 'days' => [['Intervals', ['conditioning', 'full_body']], ['Circuit', ['conditioning', 'full_body']], ['Endurance', ['conditioning', 'full_body']]]],
            ['name' => '3-Day Mobility and Core', 'goal' => 'Movement quality and trunk control', 'program_type' => 'mobility_core', 'equipment_profile' => 'minimal_equipment', 'minutes' => 35, 'description' => 'Mobility, core control, and low-impact recovery.', 'days' => [['Mobility A', ['mobility', 'core']], ['Core Control', ['core', 'mobility']], ['Mobility B', ['mobility', 'core']]]],
            ['name' => '4-Day Functional Fitness', 'goal' => 'Practical whole-body conditioning', 'program_type' => 'functional', 'equipment_profile' => 'full_gym', 'minutes' => 55, 'description' => 'A mix of carries, full-body strength, and conditioning.', 'days' => [['Full Body A', ['full_body']], ['Conditioning A', ['conditioning', 'full_body']], ['Full Body B', ['full_body']], ['Conditioning B', ['conditioning', 'full_body']]]],
        ];
    }
}
