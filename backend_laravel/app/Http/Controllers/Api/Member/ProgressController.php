<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\StoreBodyMeasurementRequest;
use App\Http\Requests\Workout\StoreProgressPhotoRequest;
use App\Http\Requests\Workout\StoreWeightLogRequest;
use App\Http\Requests\Workout\StoreWorkoutScheduleOverrideRequest;
use App\Http\Requests\Workout\UpdateWorkoutAnalyticsPreferenceRequest;
use App\Http\Resources\Workout\BodyMeasurementResource;
use App\Http\Resources\Workout\ProgressPhotoResource;
use App\Http\Resources\Workout\WeightLogResource;
use App\Models\BodyMeasurement;
use App\Models\MemberProfile;
use App\Models\MemberWorkoutPreference;
use App\Models\ProgressPhoto;
use App\Models\WeightLog;
use App\Models\WorkoutPlan;
use App\Models\WorkoutScheduleOverride;
use App\Services\Audit\AuditLogService;
use App\Services\Member\MemberAppService;
use App\Services\Workout\WorkoutAccessService;
use App\Services\Workout\WorkoutAnalyticsService;
use App\Services\Workout\WorkoutScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProgressController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MemberAppService $memberAppService,
        private readonly WorkoutAccessService $workoutAccessService,
        private readonly WorkoutAnalyticsService $workoutAnalyticsService,
        private readonly WorkoutScheduleService $workoutScheduleService,
    ) {}

    public function summary(Request $request)
    {
        $member = $request->user();
        $latestWeightLog = $member->weightLogs()
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->first();
        $latestBodyMeasurement = $member->bodyMeasurements()
            ->orderByDesc('measured_on')
            ->orderByDesc('id')
            ->first();

        return $this->success([
            'latest_weight_log' => $latestWeightLog ? WeightLogResource::make($latestWeightLog) : null,
            'latest_body_measurement' => $latestBodyMeasurement ? BodyMeasurementResource::make($latestBodyMeasurement) : null,
            'recent_progress_photos' => ProgressPhotoResource::collection($member->progressPhotos()->latest('captured_on')->take(6)->get()),
        ]);
    }

    public function weightLogs(Request $request)
    {
        $paginator = $request->user()->weightLogs()
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, WeightLogResource::collection($paginator->getCollection()), 'Weight logs fetched successfully.');
    }

    public function analytics(Request $request)
    {
        [$from, $to, $timezone] = $this->analyticsRange($request);

        return $this->success($this->workoutAnalyticsService->summary($request->user(), $from, $to, $timezone));
    }

    public function calendar(Request $request)
    {
        [$from, $to, $timezone] = $this->analyticsRange($request, 45);

        return $this->success([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'timezone' => $timezone],
            'items' => $this->workoutAnalyticsService->calendar($request->user(), $from, $to, $timezone),
        ]);
    }

    public function workoutPreferences(Request $request)
    {
        $preference = MemberWorkoutPreference::query()->firstOrCreate(['member_id' => $request->user()->id]);

        return $this->success($preference);
    }

    public function updateWorkoutPreferences(UpdateWorkoutAnalyticsPreferenceRequest $request)
    {
        $preference = $this->workoutScheduleService->savePreference($request->user(), $request->validated());

        return $this->success($preference, 'Workout preferences updated successfully.');
    }

    public function storeScheduleOverride(StoreWorkoutScheduleOverrideRequest $request)
    {
        $plan = WorkoutPlan::query()->with('days')->findOrFail($request->integer('workout_plan_id'));
        $this->workoutAccessService->assertPlanAccess($request->user(), $plan);
        $override = $this->workoutScheduleService->saveOverride($request->user(), $request->user(), $plan, $request->validated());

        return $this->success($override, 'Workout schedule updated successfully.');
    }

    public function cancelScheduleOverride(Request $request, WorkoutScheduleOverride $workoutScheduleOverride)
    {
        abort_unless((int) $workoutScheduleOverride->member_id === (int) $request->user()->id, 404);

        return $this->success($this->workoutScheduleService->cancelOverride($workoutScheduleOverride), 'Workout schedule override cancelled.');
    }

    public function storeWeightLog(StoreWeightLogRequest $request)
    {
        $memberProfile = $this->resolveMemberProfile($request);
        $weightLog = WeightLog::query()->create([
            'gym_id' => $memberProfile?->gym_id,
            'branch_id' => $memberProfile?->branch_id,
            'member_id' => $request->user()->id,
            'logged_by_user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        $this->auditLogService->log(
            event: 'progress.weight_log.created',
            action: 'create',
            request: $request,
            subject: $weightLog,
            gym: $memberProfile?->gym,
            branch: $memberProfile?->branch,
            newValues: $weightLog->toArray(),
        );

        return $this->success(WeightLogResource::make($weightLog), 'Weight log created successfully.', 201);
    }

    public function bodyMeasurements(Request $request)
    {
        $paginator = $request->user()->bodyMeasurements()
            ->orderByDesc('measured_on')
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, BodyMeasurementResource::collection($paginator->getCollection()), 'Body measurements fetched successfully.');
    }

    public function storeBodyMeasurement(StoreBodyMeasurementRequest $request)
    {
        $memberProfile = $this->resolveMemberProfile($request);
        $measurement = BodyMeasurement::query()->create([
            'gym_id' => $memberProfile?->gym_id,
            'branch_id' => $memberProfile?->branch_id,
            'member_id' => $request->user()->id,
            'logged_by_user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        $this->auditLogService->log(
            event: 'progress.body_measurement.created',
            action: 'create',
            request: $request,
            subject: $measurement,
            gym: $memberProfile?->gym,
            branch: $memberProfile?->branch,
            newValues: $measurement->toArray(),
        );

        return $this->success(BodyMeasurementResource::make($measurement), 'Body measurement created successfully.', 201);
    }

    public function photos(Request $request)
    {
        $paginator = $request->user()->progressPhotos()
            ->latest('captured_on')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, ProgressPhotoResource::collection($paginator->getCollection()), 'Progress photos fetched successfully.');
    }

    public function storePhoto(StoreProgressPhotoRequest $request)
    {
        $memberProfile = $this->resolveMemberProfile($request);
        $payload = $request->safe()->except(['photo']);
        if ($request->hasFile('photo')) {
            $storedPath = $request->file('photo')->store('member-progress-photos', 'public');
            $payload['photo_url'] = $request->getSchemeAndHttpHost().Storage::url($storedPath);
        }

        $photo = ProgressPhoto::query()->create([
            'gym_id' => $memberProfile?->gym_id,
            'branch_id' => $memberProfile?->branch_id,
            'member_id' => $request->user()->id,
            'uploaded_by_user_id' => $request->user()->id,
            ...$payload,
            'photo_type' => $request->validated('photo_type', 'other'),
        ]);

        $this->auditLogService->log(
            event: 'progress.photo.created',
            action: 'create',
            request: $request,
            subject: $photo,
            gym: $memberProfile?->gym,
            branch: $memberProfile?->branch,
            newValues: $photo->toArray(),
        );

        return $this->success(ProgressPhotoResource::make($photo), 'Progress photo created successfully.', 201);
    }

    private function resolveMemberProfile(Request $request): ?MemberProfile
    {
        return $this->memberAppService->memberProfileFor($request->user());
    }

    /** @return array{CarbonImmutable, CarbonImmutable, string} */
    private function analyticsRange(Request $request, int $defaultDays = 90): array
    {
        $validated = validator($request->query(), [
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'timezone' => ['nullable', 'timezone'],
        ])->validate();
        $timezone = $validated['timezone'] ?? $request->user()->workoutPreference?->timezone ?? 'Asia/Kolkata';
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to'], $timezone)->startOfDay() : CarbonImmutable::now($timezone)->startOfDay();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'], $timezone)->startOfDay() : $to->subDays($defaultDays - 1);
        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['from' => ['Choose a valid range of at most 367 days.']]);
        }

        return [$from, $to, $timezone];
    }
}
