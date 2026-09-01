<?php

namespace Tests\Feature\Workout;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Exercise;
use App\Models\ExerciseSubstitution;
use App\Models\Gym;
use App\Models\MemberEquipmentProfile;
use App\Models\PersonalRecord;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutPlanDay;
use App\Models\WorkoutPlanExercise;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseCatalogExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_favourite_filter_and_unfavourite_visible_exercises(): void
    {
        [$member, $exercise] = $this->memberAndExercise();

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-exercises/{$exercise->id}/favourite")
            ->assertOk()->assertJsonPath('data.is_favourite', true);
        $this->actingAs($member, 'sanctum')
            ->postJson("/api/member/workout-exercises/{$exercise->id}/favourite")
            ->assertOk();

        $this->assertDatabaseCount('member_favorite_exercises', 1);
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-exercises?favourites=1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $exercise->id)
            ->assertJsonPath('data.0.is_favourite', true);

        $this->actingAs($member, 'sanctum')
            ->deleteJson("/api/member/workout-exercises/{$exercise->id}/favourite")
            ->assertOk()->assertJsonPath('data.is_favourite', false);
        $this->assertDatabaseCount('member_favorite_exercises', 0);
    }

    public function test_member_detail_includes_history_record_and_only_curated_substitutions(): void
    {
        [$member, $exercise] = $this->memberAndExercise();
        $substitute = $this->exercise(['name' => 'Dumbbell Floor Press', 'equipment' => 'dumbbell']);
        $notCurated = $this->exercise(['name' => 'Cable Fly', 'equipment' => 'cable']);
        ExerciseSubstitution::query()->create([
            'exercise_id' => $exercise->id,
            'substitute_exercise_id' => $substitute->id,
            'reason' => 'Similar horizontal press pattern.',
            'priority' => 10,
            'requires_trainer_approval' => true,
        ]);
        $session = WorkoutSession::query()->create([
            'member_id' => $member->id,
            'started_by_user_id' => $member->id,
            'session_date' => now()->toDateString(),
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
        WorkoutSessionExercise::query()->create([
            'workout_session_id' => $session->id, 'exercise_id' => $exercise->id, 'sort_order' => 1,
        ]);
        PersonalRecord::query()->create([
            'member_id' => $member->id, 'exercise_id' => $exercise->id,
            'best_weight' => 40, 'best_reps' => 8, 'best_volume' => 320, 'achieved_at' => now(),
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/member/workout-exercises/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('data.exercise.id', $exercise->id)
            ->assertJsonPath('data.personal_record.best_weight', 40)
            ->assertJsonCount(1, 'data.recent_history')
            ->assertJsonPath('data.substitutions.0.exercise.id', $substitute->id)
            ->assertJsonPath('data.substitutions.0.selection_basis', 'curated')
            ->assertJsonMissing(['id' => $notCurated->id]);
    }

    public function test_recents_and_equipment_profiles_filter_server_side(): void
    {
        [$member, $bodyweight] = $this->memberAndExercise();
        $dumbbell = $this->exercise(['name' => 'Dumbbell Row', 'equipment' => 'dumbbell']);
        $cable = $this->exercise(['name' => 'Cable Row', 'equipment' => 'cable']);
        $session = WorkoutSession::query()->create([
            'member_id' => $member->id, 'started_by_user_id' => $member->id,
            'session_date' => now()->toDateString(), 'status' => 'active', 'started_at' => now(),
        ]);
        WorkoutSessionExercise::query()->create([
            'workout_session_id' => $session->id, 'exercise_id' => $dumbbell->id, 'sort_order' => 1,
        ]);

        $profileResponse = $this->actingAs($member, 'sanctum')->postJson('/api/member/equipment-profiles', [
            'name' => 'Travel setup', 'preset_key' => 'custom',
            'equipment' => ['dumbbell', 'bodyweight'], 'is_default' => true,
        ])->assertCreated();
        $profileId = $profileResponse->json('data.id');

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/member/workout-exercises?equipment_profile_id={$profileId}&per_page=20")
            ->assertOk()->assertJsonFragment(['id' => $dumbbell->id])
            ->assertJsonFragment(['id' => $bodyweight->id])
            ->assertJsonMissing(['id' => $cable->id]);
        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/workout-exercises?recent=1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dumbbell->id);
    }

    public function test_member_cannot_use_another_members_equipment_profile(): void
    {
        [$member] = $this->memberAndExercise();
        $other = User::factory()->create(['active_role' => RoleName::Member->value]);
        $profile = MemberEquipmentProfile::query()->create([
            'user_id' => $other->id, 'name' => 'Private', 'preset_key' => 'bodyweight_only',
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/member/workout-exercises?equipment_profile_id={$profile->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment_profile_id');
    }

    public function test_trainer_recent_catalog_and_detail_use_assigned_plan_data(): void
    {
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id, 'name' => 'Catalog Gym',
            'slug' => 'catalog-gym', 'timezone' => 'Asia/Kolkata',
            'status' => 'active', 'is_active' => true, 'approval_status' => 'approved',
            'public_listing_approval_status' => 'approved',
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id, 'name' => 'Main', 'slug' => 'catalog-main',
            'timezone' => 'Asia/Kolkata', 'status' => 'active', 'is_active' => true,
        ]);
        $trainer = User::factory()->create(['active_role' => RoleName::Trainer->value]);
        $trainer->assignRole(RoleName::Trainer->value);
        $trainer->gyms()->attach($gym->id);
        $trainer->branches()->attach($branch->id);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id, 'gym_id' => $gym->id, 'branch_id' => $branch->id,
            'status' => 'active', 'is_active' => true,
        ]);
        $member = User::factory()->create(['active_role' => RoleName::Member->value]);
        $assigned = $this->exercise(['name' => 'Assigned Press']);
        $this->exercise(['name' => 'Never Assigned']);
        $plan = WorkoutPlan::query()->create([
            'gym_id' => $gym->id, 'branch_id' => $branch->id,
            'member_id' => $member->id, 'trainer_id' => $trainer->id,
            'created_by_user_id' => $trainer->id, 'name' => 'Strength',
            'duration_weeks' => 4, 'status' => 'active', 'assigned_at' => now(),
        ]);
        $day = WorkoutPlanDay::query()->create([
            'workout_plan_id' => $plan->id, 'day_number' => 1,
        ]);
        WorkoutPlanExercise::query()->create([
            'workout_plan_day_id' => $day->id, 'exercise_id' => $assigned->id,
            'sort_order' => 1, 'sets' => 3, 'notes' => 'Use a controlled tempo.',
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->getJson('/api/trainer/exercises?recent=1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);
        $this->actingAs($trainer, 'sanctum')
            ->getJson("/api/trainer/exercises/{$assigned->id}")
            ->assertOk()
            ->assertJsonPath('data.exercise.id', $assigned->id)
            ->assertJsonPath('data.recent_coaching_notes.0.notes', 'Use a controlled tempo.');
    }

    public function test_platform_admin_can_curate_and_remove_a_substitution(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create(['active_role' => RoleName::PlatformAdmin->value]);
        $admin->assignRole(RoleName::PlatformAdmin->value);
        $exercise = $this->exercise(['name' => 'Barbell Bench Press']);
        $substitute = $this->exercise(['name' => 'Dumbbell Bench Press']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/platform-admin/exercises/{$exercise->id}/substitutions", [
                'substitute_exercise_id' => $substitute->id,
                'reason' => 'Same movement pattern with independent loading.',
                'priority' => 10,
                'requires_trainer_approval' => true,
            ])->assertCreated()
            ->assertJsonPath('data.exercise_id', $exercise->id);
        $mappingId = $response->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/platform-admin/exercises/{$exercise->id}/substitutions")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.exercise.id', $substitute->id);
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/platform-admin/exercise-substitutions/{$mappingId}")
            ->assertOk();
        $this->assertDatabaseMissing('exercise_substitutions', ['id' => $mappingId]);
    }

    private function memberAndExercise(): array
    {
        $this->seed(PermissionSeeder::class);
        $member = User::factory()->create(['active_role' => RoleName::Member->value]);
        $member->assignRole(RoleName::Member->value);

        return [$member, $this->exercise()];
    }

    private function exercise(array $attributes = []): Exercise
    {
        return Exercise::query()->create($attributes + [
            'name' => 'Push Up', 'body_part' => 'chest', 'muscle_group' => 'chest',
            'target_muscle' => 'pectorals', 'secondary_muscles' => ['triceps'],
            'equipment' => 'bodyweight', 'difficulty' => 'beginner',
            'movement_pattern' => 'push', 'default_tracking_mode' => 'reps',
            'is_bodyweight' => true, 'supports_external_load' => false,
            'is_global' => true, 'status' => 'approved', 'review_status' => 'approved', 'is_active' => true,
        ]);
    }
}
