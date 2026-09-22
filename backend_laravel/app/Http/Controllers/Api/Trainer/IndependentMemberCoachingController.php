<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreTrainerMemberNoteRequest;
use App\Http\Requests\Workout\StoreWorkoutScheduleOverrideRequest;
use App\Http\Resources\IndependentTrainerMemberRelationshipResource;
use App\Http\Resources\Trainer\TrainerMemberNoteResource;
use App\Http\Resources\Workout\BodyMeasurementResource;
use App\Http\Resources\Workout\PersonalRecordResource;
use App\Http\Resources\Workout\ProgressPhotoResource;
use App\Http\Resources\Workout\WeightLogResource;
use App\Http\Resources\Workout\WorkoutPlanResource;
use App\Http\Resources\Workout\WorkoutSessionResource;
use App\Models\IndependentTrainerMemberRelationship;
use App\Models\PersonalRecord;
use App\Models\TrainerMemberNote;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Services\Audit\AuditLogService;
use App\Services\Privacy\ConsentService;
use App\Services\Trainer\IndependentCoachingAccessService;
use App\Services\Workout\WorkoutAnalyticsService;
use App\Services\Workout\WorkoutScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class IndependentMemberCoachingController extends Controller
{
    public function __construct(
        private readonly IndependentCoachingAccessService $accessService,
        private readonly AuditLogService $auditLogService,
        private readonly WorkoutAnalyticsService $workoutAnalyticsService,
        private readonly WorkoutScheduleService $workoutScheduleService,
    ) {}

    public function show(Request $request, IndependentTrainerMemberRelationship $relationship)
    {
        $relationship = $this->relationship($request, $relationship, null);
        if (in_array('profile', $relationship->sharing_permissions ?? [], true)) {
            $coachingProfile = $relationship->member->memberProfiles()
                ->with('fitnessGoals')
                ->where('is_active', true)
                ->orderByRaw('case when gym_id is null then 0 else 1 end')
                ->latest('id')
                ->first();
            $relationship->member->setRelation('independentCoachingProfile', $coachingProfile);
        }

        return $this->success(
            IndependentTrainerMemberRelationshipResource::make($relationship),
            'Independent coaching member fetched successfully.',
        );
    }

    public function progress(Request $request, IndependentTrainerMemberRelationship $relationship)
    {
        $relationship = $this->relationship($request, $relationship, 'progress');
        $member = $relationship->member;
        $perPage = (int) $request->integer('per_page', 15);
        $weightLogs = $member->weightLogs()->whereNull('gym_id')->whereNull('branch_id')
            ->latest('log_date')->latest('id')->paginate($perPage, ['*'], 'weight_page');
        $measurements = $member->bodyMeasurements()->whereNull('gym_id')->whereNull('branch_id')
            ->latest('measured_on')->latest('id')->paginate($perPage, ['*'], 'measurement_page');
        $photos = app(ConsentService::class)->granted($member, 'photos')
            ? $member->progressPhotos()->whereNull('gym_id')->whereNull('branch_id')
                ->latest('captured_on')->latest('id')->paginate($perPage, ['*'], 'photo_page')
            : new LengthAwarePaginator([], 0, $perPage);
        $records = $member->personalRecords()
            ->with('exercise')
            ->where('coaching_scope_key', PersonalRecord::coachingScopeKey(null, null, $relationship->id))
            ->latest('best_volume')->latest('id')->paginate($perPage, ['*'], 'record_page');
        $meta = static fn ($paginator): array => [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return $this->successWithMeta([
            'relationship_id' => $relationship->id,
            'source' => 'independent',
            'member_id' => $member->id,
            'weight_logs' => WeightLogResource::collection($weightLogs->getCollection()),
            'body_measurements' => BodyMeasurementResource::collection($measurements->getCollection()),
            'progress_photos' => ProgressPhotoResource::collection($photos->getCollection()),
            'personal_records' => PersonalRecordResource::collection($records->getCollection()),
        ], [
            'weight_logs_pagination' => $meta($weightLogs),
            'body_measurements_pagination' => $meta($measurements),
            'progress_photos_pagination' => $meta($photos),
            'personal_records_pagination' => $meta($records),
        ]);
    }

    public function workoutPlans(Request $request, IndependentTrainerMemberRelationship $relationship)
    {
        $relationship = $this->relationship($request, $relationship, 'workouts');
        $paginator = WorkoutPlan::query()
            ->with(['days.exercises.exercise', 'template'])
            ->where('independent_trainer_member_relationship_id', $relationship->id)
            ->where('trainer_id', $request->user()->id)
            ->where('member_id', $relationship->member_user_id)
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(
            $paginator,
            WorkoutPlanResource::collection($paginator->getCollection()),
            'Independent member workout plans fetched successfully.',
        );
    }

    public function workoutLogbook(Request $request, IndependentTrainerMemberRelationship $relationship)
    {
        $relationship = $this->relationship($request, $relationship, 'workouts');
        $paginator = WorkoutSession::query()
            ->with(['exercises.exercise', 'exercises.sets'])
            ->where('member_id', $relationship->member_user_id)
            ->whereHas('plan', fn ($query) => $query->where('independent_trainer_member_relationship_id', $relationship->id))
            ->latest('session_date')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(
            $paginator,
            WorkoutSessionResource::collection($paginator->getCollection()),
            'Independent member workout logbook fetched successfully.',
        );
    }

    public function workoutAnalytics(Request $request, IndependentTrainerMemberRelationship $relationship)
    {
        $relationship = $this->relationship($request, $relationship, 'workouts');
        $this->relationship($request, $relationship, 'progress');
        $values = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'timezone' => ['nullable', 'timezone']]);
        $timezone = $values['timezone'] ?? 'Asia/Kolkata';
        $to = $request->filled('to') ? CarbonImmutable::parse($request->string('to'), $timezone)->startOfDay() : CarbonImmutable::now($timezone)->startOfDay();
        $from = $request->filled('from') ? CarbonImmutable::parse($request->string('from'), $timezone)->startOfDay() : $to->subDays(89);
        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['from' => ['Choose a valid range of at most 367 days.']]);
        }
        $scope = function (Builder $query) use ($relationship): Builder {
            if ($query->getModel() instanceof WorkoutPlan) {
                return $query->where('independent_trainer_member_relationship_id', $relationship->id);
            }

            return $query->whereHas('plan', fn (Builder $plan) => $plan->where('independent_trainer_member_relationship_id', $relationship->id));
        };

        $weightScope = fn ($query) => $query->whereNull('gym_id')->whereNull('branch_id');

        return $this->success($this->workoutAnalyticsService->summary($relationship->member, $from, $to, $timezone, $scope, $weightScope));
    }

    public function storeWorkoutScheduleOverride(StoreWorkoutScheduleOverrideRequest $request, IndependentTrainerMemberRelationship $relationship)
    {
        $relationship = $this->relationship($request, $relationship, 'workouts');
        $plan = WorkoutPlan::query()->with('days')
            ->whereKey($request->integer('workout_plan_id'))
            ->where('member_id', $relationship->member_user_id)
            ->where('trainer_id', $request->user()->id)
            ->where('independent_trainer_member_relationship_id', $relationship->id)
            ->firstOrFail();

        return $this->success($this->workoutScheduleService->saveOverride($request->user(), $relationship->member, $plan, $request->validated()), 'Member workout schedule updated.');
    }

    public function notes(Request $request, IndependentTrainerMemberRelationship $relationship)
    {
        $relationship = $this->relationship($request, $relationship, 'profile');
        $paginator = TrainerMemberNote::query()
            ->with(['member', 'trainer'])
            ->where('independent_trainer_member_relationship_id', $relationship->id)
            ->where('trainer_id', $request->user()->id)
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated(
            $paginator,
            TrainerMemberNoteResource::collection($paginator->getCollection()),
            'Independent trainer notes fetched successfully.',
        );
    }

    public function storeNote(
        StoreTrainerMemberNoteRequest $request,
        IndependentTrainerMemberRelationship $relationship,
    ) {
        $relationship = $this->relationship($request, $relationship, 'profile');
        $note = TrainerMemberNote::query()->create([
            'trainer_id' => $request->user()->id,
            'member_id' => $relationship->member_user_id,
            'independent_trainer_member_relationship_id' => $relationship->id,
            ...$request->validated(),
            'visibility' => 'private_to_trainer',
        ]);

        $this->auditLogService->log(
            event: 'independent_trainer.note.created',
            action: 'create',
            request: $request,
            subject: $note,
            newValues: $note->toArray(),
            context: ['relationship_id' => $relationship->id],
        );

        return $this->success(
            TrainerMemberNoteResource::make($note->load(['member', 'trainer'])),
            'Independent trainer note created successfully.',
            201,
        );
    }

    private function relationship(
        Request $request,
        IndependentTrainerMemberRelationship $relationship,
        ?string $capability,
    ): IndependentTrainerMemberRelationship {
        $member = $relationship->member()->firstOrFail();

        return $this->accessService->resolveActiveRelationship(
            $request->user(),
            $member,
            $relationship->id,
            $capability,
        );
    }
}
