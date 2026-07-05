<?php

namespace App\Services\Workout;

use App\Models\User;
use App\Models\WorkoutBook;
use App\Models\WorkoutTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkoutBookService
{
    public function __construct(
        private readonly WorkoutPlanService $workoutPlanService,
    ) {
    }

    public function createBook(User $actor, array $payload): WorkoutBook
    {
        return DB::transaction(function () use ($actor, $payload) {
            $book = WorkoutBook::query()->create([
                'created_by_user_id' => $actor->id,
                'name' => $payload['name'],
                'slug' => Str::slug($payload['name']).'-'.Str::lower(Str::random(6)),
                'audience' => $payload['audience'] ?? null,
                'goal' => $payload['goal'] ?? null,
                'difficulty' => $payload['difficulty'] ?? null,
                'program_type' => $payload['program_type'] ?? null,
                'equipment_profile' => $payload['equipment_profile'] ?? null,
                'days_per_week' => $payload['days_per_week'] ?? null,
                'duration_weeks' => $payload['duration_weeks'] ?? null,
                'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? null,
                'description' => $payload['description'] ?? null,
                'coach_notes' => $payload['coach_notes'] ?? null,
                'is_featured' => (bool) ($payload['is_featured'] ?? false),
                'status' => $payload['status'] ?? 'active',
                'published_at' => ($payload['status'] ?? 'active') === 'active' ? now() : null,
            ]);

            $this->syncBookPlans($actor, $book, $payload['plans']);

            return $book->load(['templates.days.exercises.exercise'])->loadCount('templates');
        });
    }

    public function updateBook(WorkoutBook $book, array $payload): WorkoutBook
    {
        return DB::transaction(function () use ($book, $payload) {
            $book->update([
                'name' => $payload['name'],
                'audience' => $payload['audience'] ?? null,
                'goal' => $payload['goal'] ?? null,
                'difficulty' => $payload['difficulty'] ?? null,
                'program_type' => $payload['program_type'] ?? null,
                'equipment_profile' => $payload['equipment_profile'] ?? null,
                'days_per_week' => $payload['days_per_week'] ?? null,
                'duration_weeks' => $payload['duration_weeks'] ?? null,
                'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? null,
                'description' => $payload['description'] ?? null,
                'coach_notes' => $payload['coach_notes'] ?? null,
                'is_featured' => (bool) ($payload['is_featured'] ?? false),
                'status' => $payload['status'] ?? $book->status,
                'published_at' => ($payload['status'] ?? $book->status) === 'active' ? ($book->published_at ?? now()) : null,
            ]);

            $book->templates()->each(function (WorkoutTemplate $template): void {
                $template->days()->delete();
            });
            $book->templates()->delete();

            $actor = $book->creator ?? User::query()->find($book->created_by_user_id);
            if ($actor) {
                $this->syncBookPlans($actor, $book, $payload['plans']);
            }

            return $book->fresh()->load(['templates.days.exercises.exercise'])->loadCount('templates');
        });
    }

    public function deleteBook(WorkoutBook $book): void
    {
        DB::transaction(function () use ($book): void {
            $book->templates()->each(function (WorkoutTemplate $template): void {
                $template->days()->delete();
            });
            $book->templates()->delete();
            $book->delete();
        });
    }

    private function syncBookPlans(User $actor, WorkoutBook $book, array $plans): void
    {
        foreach ($plans as $planPayload) {
            $this->workoutPlanService->createTemplateFromPayload($actor, [
                'workout_book_id' => $book->id,
                'gym_id' => null,
                'branch_id' => null,
                'name' => $planPayload['name'],
                'goal' => $planPayload['goal'] ?? $book->goal,
                'difficulty' => $planPayload['difficulty'] ?? $book->difficulty,
                'program_type' => $planPayload['program_type'] ?? $book->program_type,
                'equipment_profile' => $planPayload['equipment_profile'] ?? $book->equipment_profile,
                'duration_weeks' => $planPayload['duration_weeks'],
                'estimated_session_minutes' => $planPayload['estimated_session_minutes'] ?? $book->estimated_session_minutes,
                'weekly_schedule' => $planPayload['weekly_schedule'] ?? null,
                'notes' => $planPayload['notes'] ?? null,
                'status' => $planPayload['status'] ?? 'active',
                'is_public_catalog' => true,
                'days' => $planPayload['days'],
            ]);
        }
    }
}
