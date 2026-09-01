<?php

namespace Database\Seeders;

use App\Models\TrainerSpecialization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TrainerSpecializationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->specializations() as $index => $specialization) {
            TrainerSpecialization::query()->updateOrCreate(
                ['slug' => Str::slug($specialization['name'])],
                [
                    'name' => $specialization['name'],
                    'icon' => $specialization['icon'],
                    'description' => $specialization['description'],
                    'sort_order' => $index + 1,
                    'status' => 'active',
                    'is_active' => true,
                ],
            );
        }
    }

    /** @return array<int, array{name: string, icon: string, description: string}> */
    private function specializations(): array
    {
        return [
            ['name' => 'General fitness', 'icon' => 'fitness_center', 'description' => 'Foundational fitness programming for healthy adults.'],
            ['name' => 'Strength', 'icon' => 'fitness_center', 'description' => 'Strength training, progressive overload, and lifting technique.'],
            ['name' => 'Muscle gain and hypertrophy', 'icon' => 'exercise', 'description' => 'Resistance programs focused on muscular development.'],
            ['name' => 'Fat loss', 'icon' => 'local_fire_department', 'description' => 'Sustainable weight-management training and activity coaching.'],
            ['name' => 'Body recomposition', 'icon' => 'monitor_weight', 'description' => 'Programs balancing muscular development and fat reduction.'],
            ['name' => 'Sports conditioning', 'icon' => 'directions_run', 'description' => 'Speed, agility, power, stamina, and sport preparation.'],
            ['name' => 'Endurance and running', 'icon' => 'directions_run', 'description' => 'Aerobic conditioning and progressive run training.'],
            ['name' => 'Functional fitness', 'icon' => 'accessibility_new', 'description' => 'Movement patterns supporting everyday strength and capacity.'],
            ['name' => 'Mobility', 'icon' => 'self_improvement', 'description' => 'Mobility, flexibility, movement quality, and recovery work.'],
            ['name' => 'Corrective exercise', 'icon' => 'healing', 'description' => 'Movement assessment and exercise modification within trainer scope; not clinical diagnosis or treatment.'],
            ['name' => 'Post-rehabilitation fitness', 'icon' => 'health_and_safety', 'description' => 'Return-to-exercise support after clinical clearance and within trainer scope.'],
            ['name' => 'Senior fitness', 'icon' => 'elderly', 'description' => 'Strength, balance, mobility, and independence for older adults.'],
            ['name' => 'Youth fitness', 'icon' => 'sports', 'description' => 'Age-appropriate movement skills, confidence, and conditioning.'],
            ['name' => 'Prenatal and postnatal fitness', 'icon' => 'pregnant_woman', 'description' => 'Stage-appropriate exercise with medical clearance where required.'],
            ['name' => 'Inclusive and adaptive fitness', 'icon' => 'accessible', 'description' => 'Adapted programming for different abilities and access needs.'],
            ['name' => 'Women’s fitness', 'icon' => 'female', 'description' => 'Fitness coaching responsive to women across life stages.'],
            ['name' => 'Yoga', 'icon' => 'self_improvement', 'description' => 'Yoga-based movement, breathing, flexibility, and relaxation.'],
            ['name' => 'Pilates', 'icon' => 'accessibility_new', 'description' => 'Mat or equipment-based Pilates coaching.'],
            ['name' => 'Group fitness', 'icon' => 'groups', 'description' => 'Safe and engaging instructor-led group exercise.'],
            ['name' => 'Dance fitness', 'icon' => 'music_note', 'description' => 'Dance-led cardiovascular and group fitness sessions.'],
            ['name' => 'Boxing and combat conditioning', 'icon' => 'sports_mma', 'description' => 'Technique drills and conditioning for boxing or combat sports.'],
            ['name' => 'Powerlifting', 'icon' => 'fitness_center', 'description' => 'Squat, bench press, deadlift technique, and competition preparation.'],
            ['name' => 'Bodybuilding', 'icon' => 'exercise', 'description' => 'Physique-focused resistance training and contest preparation.'],
            ['name' => 'Nutrition and habit coaching', 'icon' => 'restaurant', 'description' => 'General nutrition, recovery, and habit support within professional scope.'],
            ['name' => 'Behaviour change coaching', 'icon' => 'psychology', 'description' => 'Motivation, adherence, goal setting, and sustainable habits.'],
            ['name' => 'Online coaching', 'icon' => 'video_call', 'description' => 'Remote assessment, programming, check-ins, and accountability.'],
        ];
    }
}
