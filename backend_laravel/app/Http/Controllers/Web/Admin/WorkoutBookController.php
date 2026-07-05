<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\UpsertWorkoutBookWebRequest;
use App\Models\Exercise;
use App\Models\WorkoutBook;
use App\Models\WorkoutTemplate;
use App\Services\Audit\AuditLogService;
use App\Services\Workout\WorkoutBookService;
use App\Support\Workout\ExerciseBookCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkoutBookController extends Controller
{
    public function __construct(
        private readonly WorkoutBookService $workoutBookService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): View
    {
        $query = WorkoutBook::query()
            ->withCount('templates')
            ->with(['creator'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('goal', 'like', $search)
                    ->orWhere('audience', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->boolean('featured_only')) {
            $query->where('is_featured', true);
        }

        $workoutBooks = $query->paginate(12)->withQueryString();

        return view('web.admin.workout-books.index', [
            'pageTitle' => 'Workout Books',
            'breadcrumbs' => ['Platform', 'Workout Books'],
            'workoutBooks' => $workoutBooks,
            'activeCount' => WorkoutBook::query()->where('status', 'active')->count(),
            'inactiveCount' => WorkoutBook::query()->where('status', 'inactive')->count(),
            'featuredCount' => WorkoutBook::query()->where('is_featured', true)->count(),
            'publishedCount' => WorkoutBook::query()->whereNotNull('published_at')->count(),
        ]);
    }

    public function create(): View
    {
        return view('web.admin.workout-books.create', [
            'pageTitle' => 'Create Workout Book',
            'breadcrumbs' => ['Platform', 'Workout Books', 'Create'],
            'workoutBook' => new WorkoutBook([
                'difficulty' => 'beginner',
                'status' => 'active',
                'days_per_week' => 3,
                'duration_weeks' => 4,
                'estimated_session_minutes' => 45,
                'is_featured' => false,
            ]),
            'plansJson' => $this->defaultPlansJson(),
            'samplePlansJson' => $this->defaultPlansJson(),
            'exerciseCatalog' => $this->exerciseCatalog(),
            'exerciseBook' => $this->exerciseBook(),
        ]);
    }

    public function store(UpsertWorkoutBookWebRequest $request): RedirectResponse
    {
        $book = $this->workoutBookService->createBook($request->user(), $request->validated());

        $this->auditLogService->log(
            'web.platform.workout_book.created',
            'create',
            $request,
            $book,
            null,
            null,
            null,
            $book->toArray(),
        );

        return redirect()
            ->route('web.admin.workout-books.edit', $book)
            ->with('status', 'Workout book created successfully.');
    }

    public function edit(WorkoutBook $workoutBook): View
    {
        $workoutBook->load([
            'creator',
            'templates.days.exercises.exercise',
        ])->loadCount('templates');

        return view('web.admin.workout-books.edit', [
            'pageTitle' => 'Edit Workout Book',
            'breadcrumbs' => ['Platform', 'Workout Books', $workoutBook->name],
            'workoutBook' => $workoutBook,
            'plansJson' => $this->plansJsonForBook($workoutBook),
            'samplePlansJson' => $this->defaultPlansJson(),
            'exerciseCatalog' => $this->exerciseCatalog(),
            'exerciseBook' => $this->exerciseBook(),
        ]);
    }

    private function exerciseCatalog(): array
    {
        return $this->exerciseQuery()
            ->get()
            ->map(fn (Exercise $exercise) => ExerciseBookCatalog::exerciseToArray($exercise))
            ->all();
    }

    private function exerciseBook(): array
    {
        return ExerciseBookCatalog::grouped($this->exerciseQuery()->get());
    }

    private function exerciseQuery()
    {
        return Exercise::query()
            ->select([
                'id',
                'name',
                'muscle_group',
                'secondary_muscles',
                'equipment',
                'difficulty',
                'instructions',
                'image_url',
                'video_url',
                'is_global',
                'status',
                'is_active',
                'created_by_user_id',
                'created_at',
                'updated_at',
            ])
            ->where(function ($query): void {
                $query->where('status', 'active')
                    ->orWhere('is_active', true);
            })
            ->orderBy('name');
    }

    public function update(
        UpsertWorkoutBookWebRequest $request,
        WorkoutBook $workoutBook,
    ): RedirectResponse {
        $oldValues = $workoutBook->load(['templates.days.exercises'])->toArray();
        $book = $this->workoutBookService->updateBook($workoutBook, $request->validated());

        $this->auditLogService->log(
            'web.platform.workout_book.updated',
            'update',
            $request,
            $book,
            null,
            null,
            $oldValues,
            $book->toArray(),
        );

        return redirect()
            ->route('web.admin.workout-books.edit', $book)
            ->with('status', 'Workout book updated successfully.');
    }

    public function destroy(Request $request, WorkoutBook $workoutBook): RedirectResponse
    {
        $oldValues = $workoutBook->load(['templates.days.exercises'])->toArray();
        $bookName = $workoutBook->name;
        $this->workoutBookService->deleteBook($workoutBook);

        $this->auditLogService->log(
            'web.platform.workout_book.deleted',
            'delete',
            $request,
            $workoutBook,
            null,
            null,
            $oldValues,
            null,
        );

        return redirect()
            ->route('web.admin.workout-books.index')
            ->with('status', $bookName.' deleted successfully.');
    }

    private function plansJsonForBook(WorkoutBook $workoutBook): string
    {
        $plans = $workoutBook->templates
            ->map(fn (WorkoutTemplate $template) => [
                'name' => $template->name,
                'goal' => $template->goal,
                'difficulty' => $template->difficulty,
                'program_type' => $template->program_type,
                'equipment_profile' => $template->equipment_profile,
                'duration_weeks' => $template->duration_weeks,
                'estimated_session_minutes' => $template->estimated_session_minutes,
                'weekly_schedule' => $template->weekly_schedule ?? [],
                'notes' => $template->notes,
                'status' => $template->status,
                'days' => $template->days->map(fn ($day) => [
                    'day_number' => $day->day_number,
                    'label' => $day->label,
                    'focus' => $day->focus,
                    'notes' => $day->notes,
                    'exercises' => $day->exercises->map(fn ($exercise) => [
                        'exercise_id' => $exercise->exercise_id,
                        'sort_order' => $exercise->sort_order,
                        'sets' => $exercise->sets,
                        'reps' => $exercise->reps,
                        'target_weight' => $exercise->target_weight,
                        'rest_seconds' => $exercise->rest_seconds,
                        'notes' => $exercise->notes,
                    ])->values()->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return json_encode($plans, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function defaultPlansJson(): string
    {
        return <<<'JSON'
[
    {
        "name": "6-Day Muscle Split",
        "goal": "Hypertrophy, body-part focus, and weekly training density",
        "difficulty": "intermediate",
        "program_type": "push_pull_legs",
        "equipment_profile": "mixed_gym",
        "duration_weeks": 8,
        "estimated_session_minutes": 70,
        "weekly_schedule": ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday"],
        "notes": "Structured as push, pull, legs, upper push, upper pull, lower posterior. Start week one with 1-2 reps in reserve and add load only after the top of the rep range is controlled.",
        "status": "active",
        "days": [
            {
                "day_number": 1,
                "label": "Push A",
                "focus": "Chest, front delts, and triceps emphasis",
                "notes": "Lead with horizontal pressing, then move to shoulder volume.",
                "exercises": [
                    {
                        "exercise_id": 6,
                        "sort_order": 1,
                        "sets": 4,
                        "reps": "6-8",
                        "target_weight": null,
                        "rest_seconds": 120,
                        "notes": "Primary chest press"
                    },
                    {
                        "exercise_id": 7,
                        "sort_order": 2,
                        "sets": 3,
                        "reps": "8-10",
                        "target_weight": null,
                        "rest_seconds": 90,
                        "notes": "Upper chest focus"
                    },
                    {
                        "exercise_id": 11,
                        "sort_order": 3,
                        "sets": 3,
                        "reps": "8-10",
                        "target_weight": null,
                        "rest_seconds": 90,
                        "notes": "Front delt and triceps drive"
                    },
                    {
                        "exercise_id": 12,
                        "sort_order": 4,
                        "sets": 3,
                        "reps": "12-15",
                        "target_weight": null,
                        "rest_seconds": 45,
                        "notes": "Lateral delt isolation"
                    },
                    {
                        "exercise_id": 5,
                        "sort_order": 5,
                        "sets": 2,
                        "reps": "AMRAP leaving 2 reps in reserve",
                        "target_weight": null,
                        "rest_seconds": 60,
                        "notes": "Bodyweight triceps and chest finisher"
                    }
                ]
            }
            ,
            {
                "day_number": 2,
                "label": "Pull A",
                "focus": "Lats, mid-back, and rear-chain posture",
                "notes": "Control the eccentric and pause on the squeeze for rows.",
                "exercises": [
                    {
                        "exercise_id": 9,
                        "sort_order": 1,
                        "sets": 4,
                        "reps": "8-10",
                        "target_weight": null,
                        "rest_seconds": 90,
                        "notes": "Vertical pull for lat width"
                    },
                    {
                        "exercise_id": 8,
                        "sort_order": 2,
                        "sets": 4,
                        "reps": "8-10",
                        "target_weight": null,
                        "rest_seconds": 90,
                        "notes": "Horizontal row for mid-back thickness"
                    },
                    {
                        "exercise_id": 10,
                        "sort_order": 3,
                        "sets": 3,
                        "reps": "10/side",
                        "target_weight": null,
                        "rest_seconds": 75,
                        "notes": "Unilateral back work"
                    },
                    {
                        "exercise_id": 16,
                        "sort_order": 4,
                        "sets": 3,
                        "reps": "8/side",
                        "target_weight": null,
                        "rest_seconds": 45,
                        "notes": "Spinal stability and rear-chain control"
                    }
                ]
            },
            {
                "day_number": 3,
                "label": "Legs A",
                "focus": "Quads, glutes, and trunk stiffness",
                "notes": "Keep torso braced and use full range on every lower-body rep.",
                "exercises": [
                    {
                        "exercise_id": 2,
                        "sort_order": 1,
                        "sets": 4,
                        "reps": "8-10",
                        "target_weight": null,
                        "rest_seconds": 120,
                        "notes": "Main quad pattern"
                    },
                    {
                        "exercise_id": 3,
                        "sort_order": 2,
                        "sets": 4,
                        "reps": "8",
                        "target_weight": null,
                        "rest_seconds": 120,
                        "notes": "Posterior-chain hinge"
                    },
                    {
                        "exercise_id": 20,
                        "sort_order": 3,
                        "sets": 3,
                        "reps": "10/side",
                        "target_weight": null,
                        "rest_seconds": 60,
                        "notes": "Unilateral leg volume"
                    },
                    {
                        "exercise_id": 14,
                        "sort_order": 4,
                        "sets": 3,
                        "reps": "45 sec",
                        "target_weight": null,
                        "rest_seconds": 45,
                        "notes": "Anti-extension core finish"
                    }
                ]
            },
            {
                "day_number": 4,
                "label": "Push B",
                "focus": "Upper chest, shoulder volume, and pressing endurance",
                "notes": "Slightly lighter than Push A with more control and total time under tension.",
                "exercises": [
                    {
                        "exercise_id": 7,
                        "sort_order": 1,
                        "sets": 4,
                        "reps": "8-10",
                        "target_weight": null,
                        "rest_seconds": 90,
                        "notes": "Lead upper push movement"
                    },
                    {
                        "exercise_id": 6,
                        "sort_order": 2,
                        "sets": 3,
                        "reps": "8-10",
                        "target_weight": null,
                        "rest_seconds": 90,
                        "notes": "Secondary chest press"
                    },
                    {
                        "exercise_id": 11,
                        "sort_order": 3,
                        "sets": 3,
                        "reps": "10-12",
                        "target_weight": null,
                        "rest_seconds": 75,
                        "notes": "Shoulder volume"
                    },
                    {
                        "exercise_id": 12,
                        "sort_order": 4,
                        "sets": 4,
                        "reps": "12-15",
                        "target_weight": null,
                        "rest_seconds": 45,
                        "notes": "Lateral delt accumulation"
                    },
                    {
                        "exercise_id": 5,
                        "sort_order": 5,
                        "sets": 3,
                        "reps": "10-15",
                        "target_weight": null,
                        "rest_seconds": 60,
                        "notes": "Pressing volume finisher"
                    }
                ]
            },
            {
                "day_number": 5,
                "label": "Pull B",
                "focus": "Back thickness, scapular control, and trunk integration",
                "notes": "Use cleaner reps than Pull A and avoid momentum on rows.",
                "exercises": [
                    {
                        "exercise_id": 8,
                        "sort_order": 1,
                        "sets": 4,
                        "reps": "10-12",
                        "target_weight": null,
                        "rest_seconds": 75,
                        "notes": "Main thickness movement"
                    },
                    {
                        "exercise_id": 9,
                        "sort_order": 2,
                        "sets": 3,
                        "reps": "10-12",
                        "target_weight": null,
                        "rest_seconds": 75,
                        "notes": "Lat pattern"
                    },
                    {
                        "exercise_id": 10,
                        "sort_order": 3,
                        "sets": 3,
                        "reps": "12/side",
                        "target_weight": null,
                        "rest_seconds": 60,
                        "notes": "Unilateral back and rear delt support"
                    },
                    {
                        "exercise_id": 15,
                        "sort_order": 4,
                        "sets": 3,
                        "reps": "10/side",
                        "target_weight": null,
                        "rest_seconds": 45,
                        "notes": "Deep core and breathing control"
                    }
                ]
            },
            {
                "day_number": 6,
                "label": "Legs B",
                "focus": "Glutes, hamstrings, single-leg control, and conditioning",
                "notes": "Posterior-chain bias with a final conditioning block instead of more heavy load.",
                "exercises": [
                    {
                        "exercise_id": 3,
                        "sort_order": 1,
                        "sets": 4,
                        "reps": "6-8",
                        "target_weight": null,
                        "rest_seconds": 120,
                        "notes": "Primary posterior-chain lift"
                    },
                    {
                        "exercise_id": 13,
                        "sort_order": 2,
                        "sets": 3,
                        "reps": "12-15",
                        "target_weight": null,
                        "rest_seconds": 60,
                        "notes": "Glute lockout work"
                    },
                    {
                        "exercise_id": 4,
                        "sort_order": 3,
                        "sets": 3,
                        "reps": "10/side",
                        "target_weight": null,
                        "rest_seconds": 60,
                        "notes": "Single-leg balance and glute demand"
                    },
                    {
                        "exercise_id": 19,
                        "sort_order": 4,
                        "sets": 5,
                        "reps": "45 sec",
                        "target_weight": null,
                        "rest_seconds": 30,
                        "notes": "Calves and conditioning"
                    }
                ]
            }
        ]
    }
]
JSON;
    }
}
