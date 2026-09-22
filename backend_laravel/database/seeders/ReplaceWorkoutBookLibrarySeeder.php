<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\User;
use App\Services\Workout\WorkoutBookLibraryBuilder;
use App\Services\Workout\WorkoutBookService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Explicitly destructive replacement. Never add this seeder to DatabaseSeeder.
 *
 * Run only after a verified backup and catalog import:
 * CONFIRM_REPLACE_WORKOUT_BOOKS=DELETE_BOOK_LINKED_HISTORY php artisan db:seed --class=ReplaceWorkoutBookLibrarySeeder --force
 */
class ReplaceWorkoutBookLibrarySeeder extends Seeder
{
    public function run(WorkoutBookLibraryBuilder $builder, WorkoutBookService $books): void
    {
        if (getenv('CONFIRM_REPLACE_WORKOUT_BOOKS') !== 'DELETE_BOOK_LINKED_HISTORY') {
            throw new RuntimeException('Replacement requires CONFIRM_REPLACE_WORKOUT_BOOKS=DELETE_BOOK_LINKED_HISTORY after a backup.');
        }

        $actor = User::query()->where('active_role', 'platform_admin')->orderBy('id')->first();
        if (! $actor) {
            throw new RuntimeException('A platform admin is required to create the replacement books.');
        }

        $exercises = Exercise::query()
            ->where('is_global', true)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();
        if ($exercises->count() < 1355) {
            throw new RuntimeException('Expected at least 1355 active, approved global exercises; found '.$exercises->count().'. No books or history were deleted.');
        }

        // Complete the catalog and programming validation before any destructive write.
        $payloads = $builder->build($exercises);

        DB::transaction(function () use ($books, $actor, $payloads): void {
            $bookIds = DB::table('workout_books')->pluck('id')->all();
            if ($bookIds !== []) {
                $templateIds = DB::table('workout_templates')->whereIn('workout_book_id', $bookIds)->pluck('id')->all();
                $planIds = $this->linkedPlanIds($bookIds, $templateIds);
                $sessionIds = $this->idsFor('workout_sessions', 'workout_plan_id', $planIds);

                // These rows otherwise survive via nullOnDelete and would retain book-linked history.
                $this->deleteFor('personal_records', 'workout_session_id', $sessionIds);
                $this->deleteFor('workout_history_import_rows', 'workout_session_id', $sessionIds);
                $this->deleteFor('workout_sessions', 'id', $sessionIds);
                $this->deleteFor('workout_plans', 'id', $planIds);
                $this->deleteFor('workout_templates', 'id', $templateIds);
                $this->deleteFor('workout_books', 'id', $bookIds);
            }

            foreach ($payloads as $payload) {
                $books->createBook($actor, $payload);
            }

            if (DB::table('workout_books')->count() !== WorkoutBookLibraryBuilder::BOOK_COUNT) {
                throw new RuntimeException('Replacement did not produce exactly 45 workout books.');
            }
        });
    }

    /** @param array<int, int> $bookIds
     * @param  array<int, int>  $templateIds
     * @return array<int, int>
     */
    private function linkedPlanIds(array $bookIds, array $templateIds): array
    {
        $ids = [];
        foreach (array_chunk($bookIds, 500) as $chunk) {
            $ids = [...$ids, ...DB::table('workout_plans')->whereIn('source_workout_book_id', $chunk)->pluck('id')->all()];
        }
        foreach (array_chunk($templateIds, 500) as $chunk) {
            $ids = [...$ids, ...DB::table('workout_plans')->whereIn('workout_template_id', $chunk)->pluck('id')->all()];
        }
        $ids = array_values(array_unique($ids));

        // Shared copies can point to a copied plan rather than directly to its original book.
        $frontier = $ids;
        while ($frontier !== []) {
            $children = $this->idsFor('workout_plans', 'source_shared_workout_plan_id', $frontier);
            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique([...$ids, ...$frontier]));
        }

        return $ids;
    }

    /** @param array<int, int> $ids
     * @return array<int, int>
     */
    private function idsFor(string $table, string $column, array $ids): array
    {
        $result = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $result = [...$result, ...DB::table($table)->whereIn($column, $chunk)->pluck('id')->all()];
        }

        return array_values(array_unique($result));
    }

    /** @param array<int, int> $ids */
    private function deleteFor(string $table, string $column, array $ids): void
    {
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table($table)->whereIn($column, $chunk)->delete();
        }
    }
}
