<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\StoreWorkoutBookRequest;
use App\Http\Requests\Workout\UpdateWorkoutBookRequest;
use App\Http\Resources\Workout\WorkoutBookResource;
use App\Models\WorkoutBook;
use App\Services\Audit\AuditLogService;
use App\Services\Workout\WorkoutBookService;
use Illuminate\Http\Request;

class WorkoutBookController extends Controller
{
    public function __construct(
        private readonly WorkoutBookService $workoutBookService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request)
    {
        $query = WorkoutBook::query()
            ->withCount('templates')
            ->with(['templates.days.exercises.exercise'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('goal', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, WorkoutBookResource::collection($paginator->getCollection()), 'Workout books fetched successfully.');
    }

    public function store(StoreWorkoutBookRequest $request)
    {
        $book = $this->workoutBookService->createBook($request->user(), $request->validated());

        $this->auditLogService->log(
            event: 'platform.workout_book.created',
            action: 'create',
            request: $request,
            subject: $book,
            newValues: $book->toArray(),
        );

        return $this->success(WorkoutBookResource::make($book), 'Workout book created successfully.', 201);
    }

    public function show(WorkoutBook $workoutBook)
    {
        return $this->success(WorkoutBookResource::make($workoutBook->load(['templates.days.exercises.exercise'])->loadCount('templates')));
    }

    public function update(UpdateWorkoutBookRequest $request, WorkoutBook $workoutBook)
    {
        $oldValues = $workoutBook->load(['templates.days.exercises'])->toArray();
        $book = $this->workoutBookService->updateBook($workoutBook, $request->validated());

        $this->auditLogService->log(
            event: 'platform.workout_book.updated',
            action: 'update',
            request: $request,
            subject: $book,
            oldValues: $oldValues,
            newValues: $book->toArray(),
        );

        return $this->success(WorkoutBookResource::make($book), 'Workout book updated successfully.');
    }

    public function destroy(Request $request, WorkoutBook $workoutBook)
    {
        $oldValues = $workoutBook->load(['templates.days.exercises'])->toArray();
        $this->workoutBookService->deleteBook($workoutBook);

        $this->auditLogService->log(
            event: 'platform.workout_book.deleted',
            action: 'delete',
            request: $request,
            subject: $workoutBook,
            oldValues: $oldValues,
            newValues: null,
        );

        return $this->success(null, 'Workout book deleted successfully.');
    }
}
