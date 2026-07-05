<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\User;
use App\Services\Workout\WorkoutBookService;
use Illuminate\Database\Seeder;

class WorkoutBookSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'platform.admin@gym.local')->first()
            ?? User::query()->where('active_role', 'platform_admin')->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        $service = app(WorkoutBookService::class);

        $exerciseIds = collect([
            ['Bodyweight Squat', 'quads', ['glutes', 'core'], 'bodyweight', 'beginner'],
            ['Goblet Squat', 'quads', ['glutes', 'core'], 'dumbbell', 'beginner'],
            ['Front Squat', 'quads', ['core', 'glutes'], 'barbell', 'advanced'],
            ['Leg Press', 'quads', ['glutes'], 'machine', 'beginner'],
            ['Romanian Deadlift', 'hamstrings', ['glutes', 'back'], 'barbell', 'intermediate'],
            ['Hamstring Curl', 'hamstrings', ['calves'], 'machine', 'beginner'],
            ['Reverse Lunge', 'quads', ['glutes', 'core'], 'bodyweight', 'beginner'],
            ['Push-Up', 'chest', ['shoulders', 'triceps'], 'bodyweight', 'beginner'],
            ['Dumbbell Bench Press', 'chest', ['shoulders', 'triceps'], 'dumbbells', 'beginner'],
            ['Incline Dumbbell Press', 'upper chest', ['shoulders', 'triceps'], 'dumbbells', 'intermediate'],
            ['Cable Fly', 'chest', ['front delts'], 'cable machine', 'beginner'],
            ['Chest Dip', 'chest', ['triceps', 'shoulders'], 'bodyweight', 'intermediate'],
            ['Seated Cable Row', 'back', ['biceps'], 'cable machine', 'beginner'],
            ['Lat Pulldown', 'back', ['biceps'], 'cable machine', 'beginner'],
            ['One-Arm Dumbbell Row', 'back', ['biceps', 'rear delts'], 'dumbbell', 'beginner'],
            ['Pull-Up', 'back', ['biceps', 'core'], 'bodyweight', 'advanced'],
            ['Face Pull', 'rear delts', ['upper back'], 'cable machine', 'beginner'],
            ['Dumbbell Shoulder Press', 'shoulders', ['triceps'], 'dumbbells', 'beginner'],
            ['Lateral Raise', 'shoulders', ['upper traps'], 'dumbbells', 'beginner'],
            ['Rear Delt Fly', 'rear delts', ['upper back'], 'dumbbells', 'beginner'],
            ['Arnold Press', 'shoulders', ['triceps'], 'dumbbells', 'intermediate'],
            ['Glute Bridge', 'glutes', ['hamstrings'], 'bodyweight', 'beginner'],
            ['Hip Thrust', 'glutes', ['hamstrings'], 'barbell', 'intermediate'],
            ['Cable Kickback', 'glutes', ['hamstrings'], 'cable machine', 'beginner'],
            ['Plank', 'core', ['shoulders'], 'bodyweight', 'beginner'],
            ['Dead Bug', 'core', ['hip flexors'], 'bodyweight', 'beginner'],
            ['Bird Dog', 'core', ['glutes', 'back'], 'bodyweight', 'beginner'],
            ['Hanging Knee Raise', 'core', ['hip flexors'], 'bodyweight', 'intermediate'],
            ['Cable Wood Chop', 'core', ['obliques'], 'cable machine', 'beginner'],
            ['Step-Up', 'quads', ['glutes'], 'bodyweight', 'beginner'],
            ['Standing Calf Raise', 'calves', ['ankles'], 'machine', 'beginner'],
            ['Seated Calf Raise', 'calves', ['ankles'], 'machine', 'beginner'],
            ['Bicep Curl', 'biceps', ['forearms'], 'dumbbells', 'beginner'],
            ['Hammer Curl', 'biceps', ['forearms'], 'dumbbells', 'beginner'],
            ['Triceps Pushdown', 'triceps', ['shoulders'], 'cable machine', 'beginner'],
            ['Overhead Triceps Extension', 'triceps', ['shoulders'], 'dumbbell', 'beginner'],
            ['Mountain Climber', 'conditioning', ['core', 'shoulders'], 'bodyweight', 'beginner'],
            ['Jump Rope', 'conditioning', ['calves'], 'bodyweight', 'beginner'],
            ['Battle Rope Slam', 'conditioning', ['shoulders', 'core'], 'battle rope', 'intermediate'],
            ['Bike Sprint', 'conditioning', ['legs'], 'stationary bike', 'beginner'],
            ['Walking Lunge', 'quads', ['glutes', 'core'], 'bodyweight', 'intermediate'],
            ['Kettlebell Swing', 'glutes', ['hamstrings', 'core'], 'kettlebell', 'intermediate'],
            ['Farmer Carry', 'full body', ['core', 'grip'], 'dumbbells', 'intermediate'],
            ['Turkish Get-Up', 'full body', ['core', 'shoulders'], 'kettlebell', 'advanced'],
            ['Cat Cow', 'mobility', ['spine'], 'bodyweight', 'beginner'],
            ['Worlds Greatest Stretch', 'mobility', ['hips', 'thoracic spine'], 'bodyweight', 'beginner'],
        ])->mapWithKeys(function (array $definition) use ($actor) {
            [$name, $muscleGroup, $secondary, $equipment, $difficulty] = $definition;

            $exercise = Exercise::query()->updateOrCreate(
                ['name' => $name, 'is_global' => true],
                [
                    'created_by_user_id' => $actor->id,
                    'muscle_group' => $muscleGroup,
                    'secondary_muscles' => $secondary,
                    'equipment' => $equipment,
                    'difficulty' => $difficulty,
                    'instructions' => 'Maintain controlled tempo and stable posture throughout each rep.',
                    'status' => 'approved',
                    'is_active' => true,
                ],
            );

            return [$name => $exercise->id];
        });

        $books = [
            [
                'name' => 'Starter Strength Book',
                'audience' => 'Beginners',
                'goal' => 'Build consistency, confidence, and full-body strength.',
                'difficulty' => 'beginner',
                'program_type' => 'full_body',
                'equipment_profile' => 'mixed_gym',
                'days_per_week' => 3,
                'duration_weeks' => 4,
                'estimated_session_minutes' => 50,
                'description' => 'A simple full-body progression built around movement quality, moderate volume, and repeatable sessions.',
                'coach_notes' => 'Keep 1-2 reps in reserve on every set and progress load only when technique stays clean.',
                'is_featured' => true,
                'status' => 'active',
                'plans' => [[
                    'name' => '3-Day Full Body Foundation',
                    'goal' => 'General strength and muscle base',
                    'difficulty' => 'beginner',
                    'program_type' => 'full_body',
                    'equipment_profile' => 'mixed_gym',
                    'duration_weeks' => 4,
                    'estimated_session_minutes' => 50,
                    'weekly_schedule' => ['monday', 'wednesday', 'friday'],
                    'notes' => 'Alternate effort across the week and focus on clean execution.',
                    'days' => [
                        ['day_number' => 1, 'label' => 'Day 1', 'focus' => 'Squat + Push', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Goblet Squat'], 'sets' => 3, 'reps' => '8-10', 'rest_seconds' => 90],
                            ['exercise_id' => $exerciseIds['Push-Up'], 'sets' => 3, 'reps' => '8-12', 'rest_seconds' => 75],
                            ['exercise_id' => $exerciseIds['Seated Cable Row'], 'sets' => 3, 'reps' => '10-12', 'rest_seconds' => 75],
                            ['exercise_id' => $exerciseIds['Plank'], 'sets' => 3, 'reps' => '30-45 sec', 'rest_seconds' => 45],
                        ]],
                        ['day_number' => 3, 'label' => 'Day 2', 'focus' => 'Hinge + Pull', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Romanian Deadlift'], 'sets' => 3, 'reps' => '8', 'rest_seconds' => 120],
                            ['exercise_id' => $exerciseIds['Lat Pulldown'], 'sets' => 3, 'reps' => '10-12', 'rest_seconds' => 75],
                            ['exercise_id' => $exerciseIds['Reverse Lunge'], 'sets' => 3, 'reps' => '8/side', 'rest_seconds' => 75],
                            ['exercise_id' => $exerciseIds['Dead Bug'], 'sets' => 3, 'reps' => '8/side', 'rest_seconds' => 45],
                        ]],
                        ['day_number' => 5, 'label' => 'Day 3', 'focus' => 'Press + Conditioning', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Dumbbell Bench Press'], 'sets' => 3, 'reps' => '8-10', 'rest_seconds' => 90],
                            ['exercise_id' => $exerciseIds['One-Arm Dumbbell Row'], 'sets' => 3, 'reps' => '10/side', 'rest_seconds' => 75],
                            ['exercise_id' => $exerciseIds['Step-Up'], 'sets' => 3, 'reps' => '10/side', 'rest_seconds' => 60],
                            ['exercise_id' => $exerciseIds['Jump Rope'], 'sets' => 6, 'reps' => '45 sec', 'rest_seconds' => 30],
                        ]],
                    ],
                ]],
            ],
            [
                'name' => 'Upper Lower Book',
                'audience' => 'Intermediate lifters',
                'goal' => 'Balanced muscle gain with repeatable weekly structure.',
                'difficulty' => 'intermediate',
                'program_type' => 'upper_lower',
                'equipment_profile' => 'full_gym',
                'days_per_week' => 4,
                'duration_weeks' => 6,
                'estimated_session_minutes' => 60,
                'description' => 'A practical 4-day split for lifters who can recover from moderate weekly volume.',
                'coach_notes' => 'Use double progression: add reps first, then add load.',
                'status' => 'active',
                'plans' => [[
                    'name' => '4-Day Upper Lower Build',
                    'goal' => 'Muscle gain and strength carryover',
                    'difficulty' => 'intermediate',
                    'program_type' => 'upper_lower',
                    'equipment_profile' => 'full_gym',
                    'duration_weeks' => 6,
                    'estimated_session_minutes' => 60,
                    'weekly_schedule' => ['monday', 'tuesday', 'thursday', 'friday'],
                    'days' => [
                        ['day_number' => 1, 'label' => 'Upper A', 'focus' => 'Horizontal push/pull', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Dumbbell Bench Press'], 'sets' => 4, 'reps' => '6-8', 'rest_seconds' => 120],
                            ['exercise_id' => $exerciseIds['Seated Cable Row'], 'sets' => 4, 'reps' => '8-10', 'rest_seconds' => 90],
                            ['exercise_id' => $exerciseIds['Dumbbell Shoulder Press'], 'sets' => 3, 'reps' => '8-10', 'rest_seconds' => 90],
                            ['exercise_id' => $exerciseIds['Lateral Raise'], 'sets' => 3, 'reps' => '12-15', 'rest_seconds' => 45],
                        ]],
                        ['day_number' => 2, 'label' => 'Lower A', 'focus' => 'Squat pattern', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Goblet Squat'], 'sets' => 4, 'reps' => '8-10', 'rest_seconds' => 120],
                            ['exercise_id' => $exerciseIds['Romanian Deadlift'], 'sets' => 3, 'reps' => '8', 'rest_seconds' => 120],
                            ['exercise_id' => $exerciseIds['Walking Lunge'], 'sets' => 3, 'reps' => '10/side', 'rest_seconds' => 60],
                            ['exercise_id' => $exerciseIds['Plank'], 'sets' => 3, 'reps' => '45 sec', 'rest_seconds' => 45],
                        ]],
                        ['day_number' => 4, 'label' => 'Upper B', 'focus' => 'Vertical push/pull', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Incline Dumbbell Press'], 'sets' => 4, 'reps' => '8-10', 'rest_seconds' => 90],
                            ['exercise_id' => $exerciseIds['Lat Pulldown'], 'sets' => 4, 'reps' => '8-10', 'rest_seconds' => 90],
                            ['exercise_id' => $exerciseIds['One-Arm Dumbbell Row'], 'sets' => 3, 'reps' => '10/side', 'rest_seconds' => 75],
                            ['exercise_id' => $exerciseIds['Lateral Raise'], 'sets' => 2, 'reps' => '15', 'rest_seconds' => 45],
                        ]],
                        ['day_number' => 5, 'label' => 'Lower B', 'focus' => 'Hip hinge + unilateral', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Romanian Deadlift'], 'sets' => 4, 'reps' => '6-8', 'rest_seconds' => 120],
                            ['exercise_id' => $exerciseIds['Step-Up'], 'sets' => 3, 'reps' => '10/side', 'rest_seconds' => 60],
                            ['exercise_id' => $exerciseIds['Glute Bridge'], 'sets' => 3, 'reps' => '12-15', 'rest_seconds' => 60],
                            ['exercise_id' => $exerciseIds['Dead Bug'], 'sets' => 3, 'reps' => '10/side', 'rest_seconds' => 45],
                        ]],
                    ],
                ]],
            ],
            [
                'name' => 'Push Pull Legs Book',
                'audience' => 'Intermediate to advanced',
                'goal' => 'Hypertrophy-focused split with clear muscle-group emphasis.',
                'difficulty' => 'intermediate',
                'program_type' => 'push_pull_legs',
                'equipment_profile' => 'full_gym',
                'days_per_week' => 3,
                'duration_weeks' => 6,
                'estimated_session_minutes' => 65,
                'description' => 'A compact PPL rotation for members who prefer a classic bodybuilding structure.',
                'coach_notes' => 'Keep a controlled eccentric and stop 1 rep short of form breakdown.',
                'status' => 'active',
                'plans' => [[
                    'name' => '3-Day Push Pull Legs',
                    'goal' => 'Lean muscle and balanced volume',
                    'difficulty' => 'intermediate',
                    'program_type' => 'push_pull_legs',
                    'equipment_profile' => 'full_gym',
                    'duration_weeks' => 6,
                    'estimated_session_minutes' => 65,
                    'weekly_schedule' => ['monday', 'wednesday', 'friday'],
                    'days' => [
                        ['day_number' => 1, 'label' => 'Push', 'focus' => 'Chest shoulders triceps', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Dumbbell Bench Press'], 'sets' => 4, 'reps' => '6-8', 'rest_seconds' => 120],
                            ['exercise_id' => $exerciseIds['Incline Dumbbell Press'], 'sets' => 3, 'reps' => '8-10', 'rest_seconds' => 90],
                            ['exercise_id' => $exerciseIds['Dumbbell Shoulder Press'], 'sets' => 3, 'reps' => '8-10', 'rest_seconds' => 90],
                            ['exercise_id' => $exerciseIds['Lateral Raise'], 'sets' => 3, 'reps' => '12-15', 'rest_seconds' => 45],
                        ]],
                        ['day_number' => 3, 'label' => 'Pull', 'focus' => 'Back rear delts biceps', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Lat Pulldown'], 'sets' => 4, 'reps' => '8-10', 'rest_seconds' => 90],
                            ['exercise_id' => $exerciseIds['Seated Cable Row'], 'sets' => 4, 'reps' => '10', 'rest_seconds' => 75],
                            ['exercise_id' => $exerciseIds['One-Arm Dumbbell Row'], 'sets' => 3, 'reps' => '10/side', 'rest_seconds' => 75],
                            ['exercise_id' => $exerciseIds['Bird Dog'], 'sets' => 3, 'reps' => '8/side', 'rest_seconds' => 45],
                        ]],
                        ['day_number' => 5, 'label' => 'Legs', 'focus' => 'Quads glutes hamstrings', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Goblet Squat'], 'sets' => 4, 'reps' => '8-10', 'rest_seconds' => 120],
                            ['exercise_id' => $exerciseIds['Romanian Deadlift'], 'sets' => 3, 'reps' => '8', 'rest_seconds' => 120],
                            ['exercise_id' => $exerciseIds['Walking Lunge'], 'sets' => 3, 'reps' => '10/side', 'rest_seconds' => 60],
                            ['exercise_id' => $exerciseIds['Glute Bridge'], 'sets' => 3, 'reps' => '12-15', 'rest_seconds' => 60],
                        ]],
                    ],
                ]],
            ],
            [
                'name' => 'Fat Loss Circuit Book',
                'audience' => 'General fitness',
                'goal' => 'Improve conditioning while maintaining full-body training volume.',
                'difficulty' => 'beginner',
                'program_type' => 'conditioning_circuit',
                'equipment_profile' => 'minimal_equipment',
                'days_per_week' => 3,
                'duration_weeks' => 4,
                'estimated_session_minutes' => 35,
                'description' => 'Shorter, denser sessions built around full-body circuits and manageable work-rest ratios.',
                'coach_notes' => 'Move continuously but keep breathing under control and technique consistent.',
                'status' => 'active',
                'plans' => [[
                    'name' => '3-Day Conditioning Circuit',
                    'goal' => 'Fat loss and movement quality',
                    'difficulty' => 'beginner',
                    'program_type' => 'conditioning_circuit',
                    'equipment_profile' => 'minimal_equipment',
                    'duration_weeks' => 4,
                    'estimated_session_minutes' => 35,
                    'weekly_schedule' => ['tuesday', 'thursday', 'saturday'],
                    'days' => [
                        ['day_number' => 2, 'label' => 'Circuit A', 'focus' => 'Squat push core', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Bodyweight Squat'], 'sets' => 4, 'reps' => '12-15', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Push-Up'], 'sets' => 4, 'reps' => '8-12', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Mountain Climber'], 'sets' => 4, 'reps' => '30 sec', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Plank'], 'sets' => 4, 'reps' => '30 sec', 'rest_seconds' => 30],
                        ]],
                        ['day_number' => 4, 'label' => 'Circuit B', 'focus' => 'Lower body and posture', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Step-Up'], 'sets' => 4, 'reps' => '10/side', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Glute Bridge'], 'sets' => 4, 'reps' => '12-15', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Bird Dog'], 'sets' => 4, 'reps' => '8/side', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Jump Rope'], 'sets' => 4, 'reps' => '45 sec', 'rest_seconds' => 30],
                        ]],
                        ['day_number' => 6, 'label' => 'Circuit C', 'focus' => 'Unilateral legs and trunk', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Reverse Lunge'], 'sets' => 4, 'reps' => '10/side', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Dead Bug'], 'sets' => 4, 'reps' => '10/side', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Mountain Climber'], 'sets' => 4, 'reps' => '30 sec', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Jump Rope'], 'sets' => 4, 'reps' => '45 sec', 'rest_seconds' => 30],
                        ]],
                    ],
                ]],
            ],
            [
                'name' => 'Home Workout Book',
                'audience' => 'Members training from home',
                'goal' => 'Stay consistent with bodyweight-first training.',
                'difficulty' => 'beginner',
                'program_type' => 'home_training',
                'equipment_profile' => 'bodyweight',
                'days_per_week' => 3,
                'duration_weeks' => 4,
                'estimated_session_minutes' => 30,
                'description' => 'A low-barrier plan for members with limited equipment and limited time.',
                'coach_notes' => 'Slow the lowering phase to make bodyweight work harder.',
                'status' => 'active',
                'plans' => [[
                    'name' => 'Home Base 3-Day',
                    'goal' => 'General fitness and consistency',
                    'difficulty' => 'beginner',
                    'program_type' => 'home_training',
                    'equipment_profile' => 'bodyweight',
                    'duration_weeks' => 4,
                    'estimated_session_minutes' => 30,
                    'weekly_schedule' => ['monday', 'wednesday', 'friday'],
                    'days' => [
                        ['day_number' => 1, 'label' => 'Home A', 'focus' => 'Lower and push', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Bodyweight Squat'], 'sets' => 3, 'reps' => '15', 'rest_seconds' => 45],
                            ['exercise_id' => $exerciseIds['Push-Up'], 'sets' => 3, 'reps' => '8-12', 'rest_seconds' => 45],
                            ['exercise_id' => $exerciseIds['Glute Bridge'], 'sets' => 3, 'reps' => '15', 'rest_seconds' => 45],
                            ['exercise_id' => $exerciseIds['Plank'], 'sets' => 3, 'reps' => '30-45 sec', 'rest_seconds' => 30],
                        ]],
                        ['day_number' => 3, 'label' => 'Home B', 'focus' => 'Single leg and trunk', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Reverse Lunge'], 'sets' => 3, 'reps' => '10/side', 'rest_seconds' => 45],
                            ['exercise_id' => $exerciseIds['Bird Dog'], 'sets' => 3, 'reps' => '8/side', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Dead Bug'], 'sets' => 3, 'reps' => '8/side', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Mountain Climber'], 'sets' => 3, 'reps' => '30 sec', 'rest_seconds' => 30],
                        ]],
                        ['day_number' => 5, 'label' => 'Home C', 'focus' => 'Conditioning and posture', 'exercises' => [
                            ['exercise_id' => $exerciseIds['Step-Up'], 'sets' => 3, 'reps' => '12/side', 'rest_seconds' => 45],
                            ['exercise_id' => $exerciseIds['Push-Up'], 'sets' => 3, 'reps' => 'AMRAP leaving 2 reps in reserve', 'rest_seconds' => 45],
                            ['exercise_id' => $exerciseIds['Jump Rope'], 'sets' => 5, 'reps' => '45 sec', 'rest_seconds' => 30],
                            ['exercise_id' => $exerciseIds['Plank'], 'sets' => 3, 'reps' => '45 sec', 'rest_seconds' => 30],
                        ]],
                    ],
                ]],
            ],
        ];

        foreach ($books as $book) {
            $exists = \App\Models\WorkoutBook::query()->where('name', $book['name'])->exists();
            if (! $exists) {
                $service->createBook($actor, $book);
            }
        }
    }
}
