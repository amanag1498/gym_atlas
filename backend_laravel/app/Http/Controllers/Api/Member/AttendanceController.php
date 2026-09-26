<?php

namespace App\Http\Controllers\Api\Member;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SmartAttendance\SmartAttendanceCheckInRequest;
use App\Http\Requests\SmartAttendance\SmartAttendanceCheckOutRequest;
use App\Http\Resources\Attendance\AttendanceLogResource;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\SmartAttendanceHub;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Member\MemberAppService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
        private readonly AttendanceService $attendanceService,
        private readonly NotificationService $notificationService,
    ) {}

    public function biometricProfile(Request $request)
    {
        $user = $request->user();
        $profile = $this->memberAppService->memberProfileFor($user);
        $attendanceStatus = $this->memberAppService->attendanceStatusFor($user, $profile);

        $membership = $this->memberAppService->attendanceMembershipFor($user, $profile);
        $branch = $profile?->branch ?? $membership?->branch;

        if (($attendanceStatus['enabled'] ?? false) !== true || ! $profile || ! $profile->gym || ! $branch) {
            return $this->success([
                'enabled' => false,
                'attendance_enabled' => (bool) ($attendanceStatus['enabled'] ?? false),
                'biometric_enabled' => false,
                'biometric_identifier' => null,
                'biometric_identifier_masked' => null,
                'check_in_status' => $attendanceStatus,
                'message' => 'Biometric attendance is unavailable until an active gym membership and biometric profile are assigned.',
            ], 'Biometric attendance is unavailable until an active gym membership and biometric profile are assigned.');
        }

        $deviceLinks = $profile->biometricMemberLinks()
            ->with('device:id,name,vendor,model,branch_id,is_active')
            ->whereNot('status', 'revoked')
            ->get();
        $enrolledLinks = $deviceLinks->where('status', 'enrolled')->where(fn ($link) => $link->device?->is_active);
        $legacyReady = $deviceLinks->isEmpty() && $profile->biometric_enabled && filled($profile->biometric_identifier);
        $biometricReady = $legacyReady || $enrolledLinks->isNotEmpty();

        return $this->success([
            'enabled' => $biometricReady,
            'attendance_enabled' => true,
            'biometric_enabled' => (bool) $profile->biometric_enabled,
            'biometric_registered' => filled($profile->biometric_identifier),
            'biometric_identifier' => null,
            'biometric_identifier_masked' => $this->maskedBiometricIdentifier($profile->biometric_identifier),
            'setup_status' => $deviceLinks->isEmpty() ? ($legacyReady ? 'legacy_enrolled' : 'not_started') : ($biometricReady ? 'enrolled' : 'pending'),
            'devices' => $deviceLinks->map(fn ($link): array => [
                'device_id' => $link->biometric_device_id,
                'name' => $link->device?->name,
                'vendor' => $link->device?->vendor,
                'model' => $link->device?->model,
                'status' => $link->status,
                'modalities' => $link->modalities,
            ])->values(),
            'branch_id' => $branch->id,
            'gym_id' => $profile->gym_id,
            'check_in_status' => $attendanceStatus,
            'message' => $biometricReady
                ? 'Biometric attendance is ready. Check in using the enrolled scanner at your gym.'
                : 'Ask your gym to enroll and enable your biometric scanner profile.',
        ], 'Member biometric attendance profile fetched successfully.');
    }

    public function smartCheckIn(SmartAttendanceCheckInRequest $request)
    {
        $user = $request->user();
        $this->memberAppService->assertRequestedGymContextAccessible($user);
        $selectedGymId = $this->memberAppService->selectedGymIdFor($user);
        $profile = $this->memberAppService->memberProfileFor($user);
        $attendanceStatus = $this->memberAppService->attendanceStatusFor($user, $profile);

        if (($attendanceStatus['enabled'] ?? false) !== true || ! $profile || ! $profile->gym) {
            throw ValidationException::withMessages([
                'member_id' => [$attendanceStatus['message'] ?? 'Attendance is unavailable for the selected gym.'],
            ]);
        }

        $hub = SmartAttendanceHub::query()
            ->with(['gym', 'branch'])
            ->where('public_id', $request->validated('hub_public_id'))
            ->first();

        if (! $hub || (int) $hub->gym_id !== (int) $selectedGymId || ! $hub->is_active || $hub->status !== 'online') {
            throw ValidationException::withMessages([
                'hub_public_id' => ['No active Smart Attendance Hub matched the selected gym.'],
            ]);
        }

        $branch = $hub->branch;
        if (! $branch) {
            $branch = $profile->branch ?: Branch::query()->find($profile->branch_id);
        }

        if (! $branch || (int) $branch->gym_id !== (int) $hub->gym_id) {
            throw ValidationException::withMessages([
                'branch_id' => ['Smart Attendance is unavailable because the hub is not linked to a valid branch context.'],
            ]);
        }

        $metadata = array_filter([
            'hub_public_id' => $hub->public_id,
            'protocol_version' => $request->integer('protocol_version'),
            'rssi' => $request->has('rssi') ? $request->integer('rssi') : null,
            'detected_at' => $request->validated('detected_at'),
            'source' => $request->validated('source'),
            'metadata' => $request->validated('metadata'),
        ], fn ($value): bool => $value !== null);

        $log = $this->attendanceService->recordSmartAttendanceCheckIn(
            gym: $hub->gym,
            branch: $branch,
            member: $user,
            hub: $hub,
            detectionMetadata: $metadata,
            detectedAt: $request->validated('detected_at'),
        )->load(['gym', 'branch']);
        if ($log->wasRecentlyCreated) {
            $this->sendSmartAttendanceWelcomeNotification($user, $log, $hub);
        }
        [, $attendanceDayEnd] = $this->attendanceService->localDayBounds($hub->gym, $branch, $log->checked_in_at);
        $attendanceTimezone = $branch->timezone ?: $hub->gym->timezone ?: config('app.timezone');
        if (! in_array($attendanceTimezone, timezone_identifiers_list(), true)) {
            $attendanceTimezone = 'UTC';
        }

        return $this->success([
            'attendance' => AttendanceLogResource::make($log),
            'check_in_status' => $this->memberAppService->attendanceStatusFor($user, $profile->fresh(['gym', 'branch'])),
            'attendance_date' => $log->checked_in_at->copy()->timezone($attendanceTimezone)->toDateString(),
            'duplicate_suppression_until' => $attendanceDayEnd->toIso8601String(),
        ], $log->wasRecentlyCreated
            ? 'Smart Attendance check-in recorded successfully.'
            : 'Smart Attendance presence updated successfully.', $log->wasRecentlyCreated ? 201 : 200);
    }

    public function smartCheckOut(SmartAttendanceCheckOutRequest $request)
    {
        $this->memberAppService->assertRequestedGymContextAccessible($request->user());
        $selectedGymId = $this->memberAppService->selectedGymIdFor($request->user());
        $log = AttendanceLog::query()->findOrFail($request->integer('attendance_log_id'));
        if ((int) $log->gym_id !== (int) $selectedGymId) {
            throw ValidationException::withMessages([
                'attendance_log_id' => ['The Smart Attendance visit was not found for the selected gym.'],
            ]);
        }
        $log = $this->attendanceService->finalizeSmartAttendanceVisit(
            $log,
            $request->user(),
            $request->validated('last_presence_at'),
        )->load(['gym', 'branch']);

        return $this->success([
            'attendance' => AttendanceLogResource::make($log),
        ], 'Smart Attendance out time saved successfully.');
    }

    private function sendSmartAttendanceWelcomeNotification(User $member, AttendanceLog $log, SmartAttendanceHub $hub): void
    {
        $gymName = $log->gym?->name ?? $hub->gym?->name ?? 'your gym';
        $branchName = $log->branch?->name ?? $hub->branch?->name;
        $time = $log->checked_in_at?->timezone(config('app.timezone'))->format('g:i A');
        $body = $time
            ? "Welcome to {$gymName}. Your Smart Attendance check-in was recorded at {$time}."
            : "Welcome to {$gymName}. Your Smart Attendance check-in was recorded.";

        $this->notificationService->create(
            user: $member,
            type: NotificationType::SmartAttendanceCheckIn->value,
            title: 'Welcome to '.$gymName,
            body: $body,
            gymId: $log->gym_id,
            branchId: $log->branch_id,
            data: [
                'app_role' => 'member',
                'attendance_log_id' => $log->id,
                'smart_attendance_hub_id' => $hub->id,
                'hub_public_id' => $hub->public_id,
                'gym_name' => $gymName,
                'branch_name' => $branchName,
                'deep_link' => '/home?section=attendance',
            ],
        );
    }

    public function history(Request $request)
    {
        $profile = $this->memberAppService->memberProfileFor($request->user());
        $attendanceStatus = $this->memberAppService->attendanceStatusFor($request->user(), $profile);

        if (($attendanceStatus['enabled'] ?? false) !== true) {
            $paginator = new LengthAwarePaginator(
                [],
                0,
                (int) $request->integer('per_page', 15),
                max(1, (int) $request->integer('page', 1)),
                ['path' => $request->url(), 'query' => $request->query()],
            );

            return $this->paginated($paginator, [], 'Attendance history will appear once an active gym membership is assigned.');
        }

        $query = AttendanceLog::query()
            ->with(['gym', 'branch'])
            ->where('member_id', $request->user()->id)
            ->where('gym_id', $profile->gym_id)
            ->when($profile->branch_id, fn ($builder) => $builder->where('branch_id', $profile->branch_id))
            ->latest('checked_in_at')
            ->latest('id');

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, AttendanceLogResource::collection($paginator->getCollection()), 'Member attendance history fetched successfully.');
    }

    public function status(Request $request)
    {
        $profile = $this->memberAppService->memberProfileFor($request->user());

        return $this->success(
            $this->memberAppService->attendanceStatusFor($request->user(), $profile),
            'Member attendance status fetched successfully.'
        );
    }

    private function maskedBiometricIdentifier(?string $identifier): ?string
    {
        if (blank($identifier)) {
            return null;
        }

        $identifier = (string) $identifier;

        return str_repeat('•', max(4, mb_strlen($identifier) - 4)).mb_substr($identifier, -4);
    }
}
