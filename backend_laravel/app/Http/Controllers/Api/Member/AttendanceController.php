<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Attendance\AttendanceLogResource;
use App\Models\AttendanceLog;
use App\Services\Attendance\AttendanceService;
use App\Services\Member\MemberAppService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
        private readonly AttendanceService $attendanceService,
    ) {}

    public function qrCode(Request $request)
    {
        $user = $request->user();
        $profile = $this->memberAppService->memberProfileFor($user);
        $attendanceStatus = $this->memberAppService->attendanceStatusFor($user, $profile);

        $gym = $profile?->gym;
        $branch = $profile?->branch ?? $this->memberAppService->attendanceMembershipFor($user, $profile)?->branch;

        if (($attendanceStatus['enabled'] ?? false) !== true || ! $gym || ! $branch) {
            return $this->success([
                'enabled' => false,
                'qr_payload' => null,
                'expires_at' => null,
                'check_in_status' => $attendanceStatus,
            ], 'Attendance QR code is unavailable until an active gym membership is assigned.');
        }

        return $this->success([
            'enabled' => true,
            ...$this->attendanceService->buildQrPayload($user, $gym, $branch),
            'check_in_status' => $attendanceStatus,
        ], 'Member QR code payload generated successfully.');
    }

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

        $biometricReady = $profile->biometric_enabled && filled($profile->biometric_identifier);

        return $this->success([
            'enabled' => $biometricReady,
            'attendance_enabled' => true,
            'biometric_enabled' => (bool) $profile->biometric_enabled,
            'biometric_registered' => filled($profile->biometric_identifier),
            'biometric_identifier' => null,
            'biometric_identifier_masked' => $this->maskedBiometricIdentifier($profile->biometric_identifier),
            'branch_id' => $branch->id,
            'gym_id' => $profile->gym_id,
            'check_in_status' => $attendanceStatus,
            'message' => $biometricReady
                ? 'Biometric attendance is ready. Check in using the enrolled scanner at your gym.'
                : 'Ask your gym to enroll and enable your biometric scanner profile.',
        ], 'Member biometric attendance profile fetched successfully.');
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
