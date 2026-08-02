<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Attendance\AttendanceLogResource;
use App\Http\Resources\Trainer\TrainerAssignedMemberResource;
use App\Http\Resources\Workout\BodyMeasurementResource;
use App\Http\Resources\Workout\PersonalRecordResource;
use App\Http\Resources\Workout\ProgressPhotoResource;
use App\Http\Resources\Workout\WeightLogResource;
use App\Http\Resources\Workout\WorkoutPlanResource;
use App\Http\Resources\Workout\WorkoutSessionResource;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Services\Member\EngagementScoreService;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;

class AssignedMemberController extends Controller
{
    public function __construct(
        private readonly TrainerScopeService $trainerScopeService,
        private readonly EngagementScoreService $engagementScoreService,
    ) {}

    public function index(Request $request)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);

        $query = $this->trainerScopeService->assignedMembersQuery($trainerProfile)
            ->when($request->filled('search'), function ($builder) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $builder->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search));
            })
            ->when($request->filled('membership_status'), fn ($builder) => $builder
                ->whereHas('memberships', fn ($membershipQuery) => $membershipQuery->where('status', $request->string('membership_status'))))
            ->when($request->boolean('payment_due_only'), fn ($builder) => $builder
                ->whereHas('memberships', fn ($membershipQuery) => $membershipQuery->where('due_amount', '>', 0)))
            ->latest('id');

        $paginator = $query->paginate((int) $request->integer('per_page', 15));
        $this->engagementScoreService->enrichMemberProfiles($paginator->getCollection(), true);

        return $this->paginated($paginator, TrainerAssignedMemberResource::collection($paginator->getCollection()), 'Assigned members fetched successfully.');
    }

    public function show(Request $request, User $member)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $memberProfile = $this->trainerScopeService->resolveAssignedMember($trainerProfile, $member);
        $this->engagementScoreService->enrichMemberProfiles([$memberProfile], true);

        return $this->success(TrainerAssignedMemberResource::make($memberProfile));
    }

    public function attendance(Request $request, User $member)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $attendanceLogs = $this->trainerScopeService->attendanceQuery($trainerProfile, $member)
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($attendanceLogs, AttendanceLogResource::collection($attendanceLogs->getCollection()), 'Attendance fetched successfully.');
    }

    public function progress(Request $request, User $member)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $memberProfile = $this->trainerScopeService->resolveAssignedMember($trainerProfile, $member);
        $perPage = (int) $request->integer('per_page', 15);
        $scope = fn ($query) => $query
            ->where('gym_id', $trainerProfile->gym_id)
            ->when($trainerProfile->branch_id, fn ($builder) => $builder->where('branch_id', $trainerProfile->branch_id));
        $weightLogs = $scope($member->weightLogs())->latest('log_date')->latest('id')->paginate($perPage, ['*'], 'weight_page');
        $measurements = $scope($member->bodyMeasurements())->latest('measured_on')->latest('id')->paginate($perPage, ['*'], 'measurement_page');
        $photos = $scope($member->progressPhotos())->latest('captured_on')->latest('id')->paginate($perPage, ['*'], 'photo_page');
        $records = $scope($member->personalRecords()->with('exercise'))->latest('best_volume')->latest('id')->paginate($perPage, ['*'], 'record_page');
        $meta = static fn ($paginator): array => [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return $this->successWithMeta([
            'member_id' => $memberProfile->user_id,
            'fitness_goal' => $memberProfile->fitness_goal,
            'height_cm' => $memberProfile->height_cm,
            'weight_kg' => $memberProfile->weight_kg,
            'experience_level' => $memberProfile->experience_level,
            'latest_note' => optional($memberProfile->trainerNotes->first())->note,
            'last_check_in_at' => optional($memberProfile->attendanceLogs->first())->checked_in_at?->toIso8601String(),
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

    public function workoutPlans(Request $request, User $member)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $this->trainerScopeService->resolveAssignedMember($trainerProfile, $member);

        $paginator = WorkoutPlan::query()
            ->with(['days.exercises.exercise', 'template'])
            ->where('member_id', $member->id)
            ->where('trainer_id', $trainerProfile->user_id)
            ->where('gym_id', $trainerProfile->gym_id)
            ->whereNull('independent_trainer_member_relationship_id')
            ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id))
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, WorkoutPlanResource::collection($paginator->getCollection()), 'Member workout plans fetched successfully.');
    }

    public function workoutLogbook(Request $request, User $member)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $this->trainerScopeService->resolveAssignedMember($trainerProfile, $member);

        $paginator = WorkoutSession::query()
            ->with('exercises.exercise', 'exercises.sets')
            ->where('member_id', $member->id)
            ->where('gym_id', $trainerProfile->gym_id)
            ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id))
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        $records = $member->personalRecords()
            ->with('exercise')
            ->where('gym_id', $trainerProfile->gym_id)
            ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id))
            ->orderByDesc('best_volume')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15), ['*'], 'records_page');

        return $this->success([
            'history' => WorkoutSessionResource::collection($paginator->getCollection()),
            'personal_records' => PersonalRecordResource::collection($records->getCollection()),
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'personal_records_pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ],
        ]);
    }
}
