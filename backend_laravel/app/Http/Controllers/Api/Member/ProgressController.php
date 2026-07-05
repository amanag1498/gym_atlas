<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workout\StoreBodyMeasurementRequest;
use App\Http\Requests\Workout\StoreProgressPhotoRequest;
use App\Http\Requests\Workout\StoreWeightLogRequest;
use App\Http\Resources\Workout\BodyMeasurementResource;
use App\Http\Resources\Workout\ProgressPhotoResource;
use App\Http\Resources\Workout\WeightLogResource;
use App\Models\MemberProfile;
use App\Models\BodyMeasurement;
use App\Models\ProgressPhoto;
use App\Models\WeightLog;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

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
        $paginator = $request->user()->progressPhotos()->latest('captured_on')->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, ProgressPhotoResource::collection($paginator->getCollection()), 'Progress photos fetched successfully.');
    }

    public function storePhoto(StoreProgressPhotoRequest $request)
    {
        $memberProfile = $this->resolveMemberProfile($request);
        $photo = ProgressPhoto::query()->create([
            'gym_id' => $memberProfile?->gym_id,
            'branch_id' => $memberProfile?->branch_id,
            'member_id' => $request->user()->id,
            'uploaded_by_user_id' => $request->user()->id,
            ...$request->validated(),
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
        return $request->user()->loadMissing('memberProfile.gym', 'memberProfile.branch')->memberProfile;
    }
}
