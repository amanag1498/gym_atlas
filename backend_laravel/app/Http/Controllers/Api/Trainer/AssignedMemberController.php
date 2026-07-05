<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Trainer\TrainerAssignedMemberResource;
use App\Http\Resources\Workout\PersonalRecordResource;
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
    ) {
    }

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

        return $this->paginated($attendanceLogs, \App\Http\Resources\Attendance\AttendanceLogResource::collection($attendanceLogs->getCollection()), 'Attendance fetched successfully.');
    }

    public function progress(Request $request, User $member)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $memberProfile = $this->trainerScopeService->resolveAssignedMember($trainerProfile, $member);

        return $this->success([
            'member_id' => $memberProfile->user_id,
            'fitness_goal' => $memberProfile->fitness_goal,
            'height_cm' => $memberProfile->height_cm,
            'weight_kg' => $memberProfile->weight_kg,
            'experience_level' => $memberProfile->experience_level,
            'latest_note' => optional($memberProfile->trainerNotes->first())->note,
            'last_check_in_at' => optional($memberProfile->attendanceLogs->first())->checked_in_at?->toIso8601String(),
            'weight_logs' => \App\Http\Resources\Workout\WeightLogResource::collection($member->weightLogs()->latest('log_date')->take(10)->get()),
            'body_measurements' => \App\Http\Resources\Workout\BodyMeasurementResource::collection($member->bodyMeasurements()->latest('measured_on')->take(10)->get()),
            'progress_photos' => \App\Http\Resources\Workout\ProgressPhotoResource::collection($member->progressPhotos()->latest('captured_on')->take(12)->get()),
            'personal_records' => PersonalRecordResource::collection($member->personalRecords()->with('exercise')->get()),
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
            ->orderByDesc('session_date')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->success([
            'history' => WorkoutSessionResource::collection($paginator->getCollection()),
            'personal_records' => PersonalRecordResource::collection($member->personalRecords()->with('exercise')->get()),
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }
}
