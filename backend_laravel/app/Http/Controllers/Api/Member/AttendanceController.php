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

        if (($attendanceStatus['enabled'] ?? false) !== true || ! $profile?->gym || ! $profile->branch) {
            return $this->success([
                'enabled' => false,
                'qr_payload' => null,
                'expires_at' => null,
                'check_in_status' => $attendanceStatus,
            ], 'Attendance QR code is unavailable until an active gym membership is assigned.');
        }

        return $this->success([
            'enabled' => true,
            ...$this->attendanceService->buildQrPayload($user, $profile->gym, $profile->branch),
            'check_in_status' => $attendanceStatus,
        ], 'Member QR code payload generated successfully.');
    }

    public function biometricProfile(Request $request)
    {
        $user = $request->user();
        $profile = $this->memberAppService->memberProfileFor($user);
        $attendanceStatus = $this->memberAppService->attendanceStatusFor($user, $profile);

        if (($attendanceStatus['enabled'] ?? false) !== true || ! $profile || ! $profile->gym || ! $profile->branch) {
            return $this->success([
                'enabled' => false,
                'biometric_enabled' => false,
                'biometric_identifier' => null,
                'check_in_status' => $attendanceStatus,
                'message' => 'Biometric attendance is unavailable until an active gym membership and biometric profile are assigned.',
            ], 'Biometric attendance is unavailable until an active gym membership and biometric profile are assigned.');
        }

        return $this->success([
            'enabled' => true,
            'biometric_enabled' => (bool) $profile->biometric_enabled,
            'biometric_identifier' => $profile->biometric_identifier,
            'branch_id' => $profile->branch_id,
            'gym_id' => $profile->gym_id,
            'check_in_status' => $attendanceStatus,
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
            ->latest('checked_in_at');

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
}
