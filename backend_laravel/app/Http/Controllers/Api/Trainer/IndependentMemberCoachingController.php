<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreTrainerMemberNoteRequest;
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
use App\Services\Trainer\IndependentCoachingAccessService;
use Illuminate\Http\Request;

class IndependentMemberCoachingController extends Controller
{
    public function __construct(
        private readonly IndependentCoachingAccessService $accessService,
        private readonly AuditLogService $auditLogService,
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
        $photos = $member->progressPhotos()->whereNull('gym_id')->whereNull('branch_id')
            ->latest('captured_on')->latest('id')->paginate($perPage, ['*'], 'photo_page');
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
