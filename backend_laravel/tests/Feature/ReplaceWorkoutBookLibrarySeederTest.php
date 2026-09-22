<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkoutBook;
use Database\Seeders\ReplaceWorkoutBookLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ReplaceWorkoutBookLibrarySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('CONFIRM_REPLACE_WORKOUT_BOOKS');
        parent::tearDown();
    }

    public function test_replacement_refuses_to_delete_when_catalog_is_incomplete(): void
    {
        User::factory()->create(['active_role' => 'platform_admin']);
        WorkoutBook::query()->create(['name' => 'Keep Until Ready', 'slug' => 'keep-until-ready']);
        putenv('CONFIRM_REPLACE_WORKOUT_BOOKS=DELETE_BOOK_LINKED_HISTORY');

        try {
            $this->seed(ReplaceWorkoutBookLibrarySeeder::class);
            $this->fail('An incomplete catalog must stop replacement.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('at least 1355', $exception->getMessage());
        }

        $this->assertDatabaseHas('workout_books', ['slug' => 'keep-until-ready']);
    }

    public function test_replacement_removes_book_lineage_and_covers_entire_catalog_without_touching_unrelated_history(): void
    {
        $admin = User::factory()->create(['active_role' => 'platform_admin']);
        $member = User::factory()->create();
        $now = now();
        $parts = ['chest', 'back', 'shoulders', 'biceps', 'core', 'glutes', 'quads', 'hamstrings', 'calves', 'full body', 'conditioning', 'mobility'];
        $rows = [];
        for ($index = 1; $index <= 1355; $index++) {
            $rows[] = [
                'name' => 'Catalog Exercise '.$index,
                'muscle_group' => $parts[$index % count($parts)],
                'equipment' => intdiv($index, 36) % 3 === 0 ? 'bodyweight' : 'dumbbell',
                'difficulty' => ['beginner', 'intermediate', 'advanced'][intdiv($index, 12) % 3],
                'is_global' => true,
                'is_active' => true,
                'status' => 'approved',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('exercises')->insert($chunk);
        }

        $oldBook = WorkoutBook::query()->create(['name' => 'Old Book', 'slug' => 'old-book', 'created_by_user_id' => $admin->id]);
        $templateId = DB::table('workout_templates')->insertGetId([
            'workout_book_id' => $oldBook->id,
            'created_by_user_id' => $admin->id,
            'name' => 'Old Template',
            'duration_weeks' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $oldPlanId = DB::table('workout_plans')->insertGetId([
            'member_id' => $member->id,
            'source_workout_book_id' => $oldBook->id,
            'workout_template_id' => $templateId,
            'name' => 'Old Adoption',
            'duration_weeks' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sharedCopyId = DB::table('workout_plans')->insertGetId([
            'member_id' => $member->id,
            'source_shared_workout_plan_id' => $oldPlanId,
            'name' => 'Shared Copy',
            'duration_weeks' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $independentPlanId = DB::table('workout_plans')->insertGetId([
            'member_id' => $member->id,
            'name' => 'Independent Plan',
            'duration_weeks' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $oldSessionId = $this->makeSession($member->id, $sharedCopyId);
        $independentSessionId = $this->makeSession($member->id, $independentPlanId);
        DB::table('personal_records')->insert([
            'member_id' => $member->id,
            'exercise_id' => 1,
            'workout_session_id' => $oldSessionId,
            'best_weight' => 20,
            'best_reps' => 8,
            'best_volume' => 160,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        putenv('CONFIRM_REPLACE_WORKOUT_BOOKS=DELETE_BOOK_LINKED_HISTORY');
        $this->seed(ReplaceWorkoutBookLibrarySeeder::class);

        $this->assertSame(45, DB::table('workout_books')->count());
        $this->assertSame(45, DB::table('workout_books')->distinct()->count('name'));
        $this->assertSame(15, DB::table('workout_books')->where('difficulty', 'beginner')->count());
        $this->assertSame(15, DB::table('workout_books')->where('difficulty', 'intermediate')->count());
        $this->assertSame(15, DB::table('workout_books')->where('difficulty', 'advanced')->count());
        $this->assertSame(45, DB::table('workout_templates')->whereNotNull('workout_book_id')->count());
        $this->assertSame(1355, DB::table('workout_template_exercises')->distinct()->count('exercise_id'));
        $this->assertGreaterThanOrEqual(5, DB::table('workout_template_exercises')
            ->selectRaw('COUNT(*) as exercise_count')
            ->groupBy('workout_template_day_id')
            ->orderBy('exercise_count')
            ->first()->exercise_count);
        $this->assertDatabaseMissing('workout_plans', ['id' => $oldPlanId]);
        $this->assertDatabaseMissing('workout_plans', ['id' => $sharedCopyId]);
        $this->assertDatabaseMissing('workout_sessions', ['id' => $oldSessionId]);
        $this->assertDatabaseMissing('personal_records', ['workout_session_id' => $oldSessionId]);
        $this->assertDatabaseHas('workout_plans', ['id' => $independentPlanId]);
        $this->assertDatabaseHas('workout_sessions', ['id' => $independentSessionId]);
    }

    private function makeSession(int $memberId, int $planId): int
    {
        return DB::table('workout_sessions')->insertGetId([
            'member_id' => $memberId,
            'started_by_user_id' => $memberId,
            'workout_plan_id' => $planId,
            'session_date' => now()->toDateString(),
            'started_at' => now(),
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
