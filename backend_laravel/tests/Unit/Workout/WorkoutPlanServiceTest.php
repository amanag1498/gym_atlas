<?php

namespace Tests\Unit\Workout;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Exercise;
use App\Models\Gym;
use App\Models\WorkoutTemplate;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Workout\WorkoutPlanService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_template_clones_plan_without_mutating_template(): void
    {
        $this->seed(PermissionSeeder::class);

        $trainer = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
        ]);
        $trainer->assignRole(RoleName::Trainer->value);

        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Clone Gym',
            'slug' => 'clone-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_approval_status' => 'approved',
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Clone Branch',
            'slug' => 'clone-branch',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        $trainer->gyms()->attach($gym->id);
        $trainer->branches()->attach($branch->id);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specializations' => ['strength'],
            'experience_years' => 5,
            'certifications' => ['ACE'],
            'languages' => ['English'],
            'is_active' => true,
            'verification_status' => 'pending',
        ]);

        $member = User::factory()->create();
        $member->assignRole(RoleName::Member->value);

        $exercise = Exercise::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $trainer->id,
            'name' => 'Bench Press',
            'muscle_group' => 'chest',
            'is_global' => false,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $service = app(WorkoutPlanService::class);
        $template = $service->createTemplateFromPayload($trainer, [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Push Template',
            'duration_weeks' => 4,
            'days' => [
                [
                    'day_number' => 1,
                    'exercises' => [
                        [
                            'exercise_id' => $exercise->id,
                            'sets' => 4,
                            'reps' => '8',
                        ],
                    ],
                ],
            ],
        ]);

        $plans = $service->assignTemplateToMembers($trainer, $template->fresh('days.exercises'), [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_ids' => [$member->id],
        ]);

        $plan = $plans->first();
        $service->updatePlan($plan, [
            'name' => 'Push Template - Modified',
            'duration_weeks' => 6,
            'days' => [
                [
                    'day_number' => 1,
                    'exercises' => [
                        [
                            'exercise_id' => $exercise->id,
                            'sets' => 6,
                            'reps' => '10',
                        ],
                    ],
                ],
            ],
        ]);

        $template = WorkoutTemplate::query()->with('days.exercises')->findOrFail($template->id);

        $this->assertSame('Push Template', $template->name);
        $this->assertSame(4, $template->duration_weeks);
        $this->assertSame(4, $template->days->first()->exercises->first()->sets);
    }
}
