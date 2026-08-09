<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutBook;
use App\Services\Workout\WorkoutBookService;
use Illuminate\Database\Seeder;

class AdvancedWorkoutPlanSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'platform.admin@gym.local')->first()
            ?? User::query()->where('active_role', 'platform_admin')->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        $exerciseIds = Exercise::query()->where('is_global', true)->pluck('id', 'name');
        $service = app(WorkoutBookService::class);

        foreach ($this->books($exerciseIds->all()) as $book) {
            if (! WorkoutBook::query()->where('name', $book['name'])->exists()) {
                $service->createBook($actor, $book);
            }
        }
    }

    /** @param array<string, int> $ids */
    private function books(array $ids): array
    {
        $exercise = static fn (string $name, int $sets, string $reps, int $rest, ?string $notes = null): array => [
            'exercise_id' => $ids[$name],
            'sets' => $sets,
            'reps' => $reps,
            'rest_seconds' => $rest,
            'notes' => $notes,
        ];

        return [
            $this->book(
                'Essential Two-Day Strength Book', 'New and returning members', 'Build a sustainable whole-body strength habit.',
                'beginner', 'full_body', 'mixed_gym', 2, 6, 50,
                'Two non-consecutive sessions cover every major movement pattern with manageable volume.',
                [[
                    'name' => 'Two-Day Total Body Foundation', 'weekly_schedule' => ['monday', 'thursday'],
                    'days' => [
                        $this->day(1, 'Full Body A', 'Squat, horizontal push and pull', [
                            $exercise('Goblet Squat', 3, '8-10', 90), $exercise('Dumbbell Bench Press', 3, '8-10', 90),
                            $exercise('Seated Cable Row', 3, '10-12', 75), $exercise('Romanian Deadlift', 2, '10', 90),
                            $exercise('Pallof Press', 2, '10/side', 45),
                        ]),
                        $this->day(2, 'Full Body B', 'Hinge, vertical push and pull', [
                            $exercise('Trap Bar Deadlift', 3, '6-8', 120), $exercise('Dumbbell Shoulder Press', 3, '8-10', 90),
                            $exercise('Lat Pulldown', 3, '10-12', 75), $exercise('Step-Up', 2, '10/side', 60),
                            $exercise('Farmer Carry', 3, '30-40 m', 60),
                        ]),
                    ],
                ]],
            ),
            $this->book(
                'Dumbbell-Only Training Book', 'Home and compact-gym trainees', 'Develop strength and muscle with dumbbells and a bench.',
                'beginner', 'upper_lower', 'dumbbells', 4, 6, 45,
                'A complete four-day split requiring only dumbbells, a bench, and bodyweight.',
                [[
                    'name' => 'Four-Day Dumbbell Build', 'weekly_schedule' => ['monday', 'tuesday', 'thursday', 'saturday'],
                    'days' => [
                        $this->day(1, 'Upper 1', 'Chest and back', [$exercise('Dumbbell Bench Press', 4, '8-12', 90), $exercise('One-Arm Dumbbell Row', 4, '8-12/side', 75), $exercise('Dumbbell Shoulder Press', 3, '10', 75), $exercise('Rear Delt Fly', 3, '12-15', 45), $exercise('Hammer Curl', 2, '12', 45)]),
                        $this->day(2, 'Lower 1', 'Squat and unilateral legs', [$exercise('Goblet Squat', 4, '10-12', 90), $exercise('Bulgarian Split Squat', 3, '8-10/side', 75), $exercise('Glute Bridge', 3, '12-15', 60), $exercise('Standing Calf Raise', 3, '15-20', 45), $exercise('Dead Bug', 3, '8/side', 45)]),
                        $this->day(3, 'Upper 2', 'Shoulders and arms', [$exercise('Incline Dumbbell Press', 4, '8-12', 90), $exercise('Chest-Supported Row', 4, '10-12', 75), $exercise('Lateral Raise', 3, '12-15', 45), $exercise('Bicep Curl', 3, '10-12', 45), $exercise('Overhead Triceps Extension', 3, '10-12', 45)]),
                        $this->day(4, 'Lower 2', 'Hinge and glutes', [$exercise('Romanian Deadlift', 4, '8-12', 90), $exercise('Walking Lunge', 3, '10/side', 60), $exercise('Hip Thrust', 3, '10-15', 75), $exercise('Suitcase Carry', 3, '30 m/side', 60), $exercise('Side Plank', 2, '30 sec/side', 45)]),
                    ],
                ]],
            ),
            $this->book(
                'Foundational Barbell Strength Book', 'Intermediate strength trainees', 'Practice the major barbell lifts with simple progression.',
                'intermediate', 'strength', 'barbell_gym', 3, 8, 65,
                'Three full-body sessions balance squat, press, pull, and hinge practice.',
                [[
                    'name' => 'Three-Day Barbell Progression', 'weekly_schedule' => ['monday', 'wednesday', 'friday'],
                    'days' => [
                        $this->day(1, 'Strength A', 'Back squat and bench', [$exercise('Back Squat', 4, '5', 180, 'Add load only after all repetitions remain technically consistent.'), $exercise('Barbell Bench Press', 4, '5', 180), $exercise('Bent-Over Barbell Row', 3, '6-8', 120), $exercise('Pallof Press', 3, '10/side', 45)]),
                        $this->day(2, 'Strength B', 'Deadlift and overhead press', [$exercise('Conventional Deadlift', 3, '4-5', 180), $exercise('Barbell Overhead Press', 4, '5', 150), $exercise('Assisted Pull-Up', 3, '6-10', 90), $exercise('Farmer Carry', 3, '30 m', 60)]),
                        $this->day(3, 'Strength C', 'Front squat and upper strength', [$exercise('Front Squat', 4, '5-6', 150), $exercise('Close-Grip Bench Press', 3, '6-8', 120), $exercise('Chest-Supported Row', 3, '8-10', 90), $exercise('Romanian Deadlift', 3, '8', 120), $exercise('Side Plank', 3, '30 sec/side', 45)]),
                    ],
                ]],
            ),
            $this->book(
                'Five-Day Hypertrophy Book', 'Experienced gym members', 'Accumulate balanced weekly muscle-building volume.',
                'advanced', 'hypertrophy', 'full_gym', 5, 8, 70,
                'A five-day structure distributes volume across all major muscle groups without requiring failure training.',
                [[
                    'name' => 'Five-Day Balanced Hypertrophy', 'weekly_schedule' => ['monday', 'tuesday', 'wednesday', 'friday', 'saturday'],
                    'days' => [
                        $this->day(1, 'Chest + Triceps', 'Horizontal and incline pressing', [$exercise('Barbell Bench Press', 4, '6-8', 120), $exercise('Incline Dumbbell Press', 3, '8-12', 90), $exercise('Cable Fly', 3, '12-15', 60), $exercise('Triceps Pushdown', 3, '10-15', 60), $exercise('Overhead Triceps Extension', 2, '12-15', 60)]),
                        $this->day(2, 'Back + Biceps', 'Vertical and horizontal pulling', [$exercise('Pull-Up', 4, '5-8', 120), $exercise('Seated Cable Row', 4, '8-12', 90), $exercise('Straight-Arm Pulldown', 3, '12-15', 60), $exercise('Face Pull', 3, '15', 45), $exercise('Incline Dumbbell Curl', 3, '10-12', 60)]),
                        $this->day(3, 'Quads + Calves', 'Knee-dominant legs', [$exercise('Back Squat', 4, '6-8', 150), $exercise('Leg Press', 3, '10-12', 120), $exercise('Bulgarian Split Squat', 3, '10/side', 90), $exercise('Leg Extension', 3, '12-15', 60), $exercise('Standing Calf Raise', 4, '10-15', 60)]),
                        $this->day(4, 'Shoulders + Arms', 'Delts and arm accessories', [$exercise('Dumbbell Shoulder Press', 4, '8-10', 90), $exercise('Cable Lateral Raise', 4, '12-15', 45), $exercise('Rear Delt Fly', 3, '12-15', 45), $exercise('Cable Curl', 3, '10-12', 60), $exercise('Skull Crusher', 3, '10-12', 60)]),
                        $this->day(5, 'Hamstrings + Glutes', 'Hip-dominant legs', [$exercise('Romanian Deadlift', 4, '6-10', 150), $exercise('Hip Thrust', 4, '8-12', 120), $exercise('Hamstring Curl', 3, '10-15', 60), $exercise('Walking Lunge', 3, '12/side', 75), $exercise('Seated Calf Raise', 4, '12-20', 60)]),
                    ],
                ]],
            ),
            $this->book(
                'Mobility and Movement Book', 'All training levels', 'Improve movement preparation, trunk control, and recovery-day activity.',
                'beginner', 'mobility', 'bodyweight_bands', 3, 4, 25,
                'Low-load sessions can be selected independently as warm-up, recovery, or movement-practice days.',
                [[
                    'name' => 'Three-Day Mobility Reset', 'weekly_schedule' => ['tuesday', 'thursday', 'sunday'],
                    'days' => [
                        $this->day(1, 'Hips + Ankles', 'Lower-body mobility', [$exercise('90/90 Hip Rotation', 3, '6/side', 30), $exercise('Ankle Dorsiflexion Mobilization', 3, '10/side', 30), $exercise('Worlds Greatest Stretch', 2, '5/side', 30), $exercise('Glute Bridge', 3, '12', 45), $exercise('Dead Bug', 3, '8/side', 30)]),
                        $this->day(2, 'Upper Back + Shoulders', 'Posture and shoulder control', [$exercise('Cat Cow', 2, '8', 30), $exercise('Thoracic Rotation', 3, '6/side', 30), $exercise('Band Pull-Apart', 3, '15', 30), $exercise('Face Pull', 3, '12-15', 45), $exercise('Bird Dog', 3, '8/side', 30)]),
                        $this->day(3, 'Whole-Body Flow', 'Integrated movement', [$exercise('Worlds Greatest Stretch', 2, '5/side', 30), $exercise('Bodyweight Squat', 3, '10', 45), $exercise('Incline Push-Up', 3, '8-12', 45), $exercise('Band Row', 3, '12', 45), $exercise('Suitcase Carry', 3, '20 m/side', 45)]),
                    ],
                ]],
            ),
            $this->book(
                'Low-Impact Fitness Book', 'Older adults and members returning to exercise', 'Build confidence, balance, and general capacity with scalable movements.',
                'beginner', 'general_fitness', 'minimal_equipment', 3, 6, 35,
                'A conservative low-impact program; members with medical limitations should obtain individual professional guidance.',
                [[
                    'name' => 'Three-Day Low-Impact Foundation', 'weekly_schedule' => ['monday', 'wednesday', 'friday'],
                    'days' => [
                        $this->day(1, 'Strength + Posture', 'Supported full body', [$exercise('Box Squat', 3, '8-10', 75), $exercise('Incline Push-Up', 3, '8-12', 60), $exercise('Band Row', 3, '10-12', 60), $exercise('Standing Calf Raise', 2, '12-15', 45), $exercise('Pallof Press', 2, '8/side', 45)]),
                        $this->day(2, 'Hips + Balance', 'Unilateral control', [$exercise('Step-Up', 3, '8/side', 60), $exercise('Glute Bridge', 3, '12', 60), $exercise('Band Romanian Deadlift', 3, '10', 60), $exercise('Suitcase Carry', 3, '20 m/side', 60), $exercise('Ankle Dorsiflexion Mobilization', 2, '8/side', 30)]),
                        $this->day(3, 'Movement + Capacity', 'Low-impact conditioning', [$exercise('Bodyweight Squat', 3, '10', 60), $exercise('Band Chest Press', 3, '10', 60), $exercise('Band Row', 3, '10', 60), $exercise('Bike Sprint', 6, '30 sec easy-moderate', 60), $exercise('Bird Dog', 2, '8/side', 30)]),
                    ],
                ]],
            ),
        ];
    }

    private function book(string $name, string $audience, string $goal, string $difficulty, string $type, string $equipment, int $days, int $weeks, int $minutes, string $description, array $plans): array
    {
        return ['name' => $name, 'audience' => $audience, 'goal' => $goal, 'difficulty' => $difficulty, 'program_type' => $type, 'equipment_profile' => $equipment, 'days_per_week' => $days, 'duration_weeks' => $weeks, 'estimated_session_minutes' => $minutes, 'description' => $description, 'coach_notes' => 'Start conservatively, retain clean technique, and adjust load, range, or exercise choice to the individual.', 'status' => 'active', 'plans' => array_map(fn (array $plan): array => $plan + ['goal' => $goal, 'difficulty' => $difficulty, 'program_type' => $type, 'equipment_profile' => $equipment, 'duration_weeks' => $weeks, 'estimated_session_minutes' => $minutes, 'status' => 'active'], $plans)];
    }

    private function day(int $number, string $label, string $focus, array $exercises): array
    {
        return ['day_number' => $number, 'label' => $label, 'focus' => $focus, 'exercises' => $exercises];
    }
}
