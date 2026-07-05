<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceCheckInMethod;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {
    }

    public function request(
        Gym $gym,
        Branch $branch,
        User $member,
        User $requestedBy,
        Carbon|string $requestedCheckInAt,
        string $reason,
        ?AttendanceLog $attendanceLog = null,
    ): AttendanceCorrectionRequest {
        $requestedAt = $requestedCheckInAt instanceof Carbon
            ? $requestedCheckInAt
            : Carbon::parse($requestedCheckInAt);

        if ($attendanceLog && ((int) $attendanceLog->gym_id !== $gym->id || (int) $attendanceLog->member_id !== $member->id)) {
            throw ValidationException::withMessages([
                'attendance_log_id' => ['The selected attendance log does not match this member and gym scope.'],
            ]);
        }

        return AttendanceCorrectionRequest::query()->create([
            'attendance_log_id' => $attendanceLog?->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'requested_by' => $requestedBy->id,
            'status' => 'pending',
            'reason' => $reason,
            'requested_check_in_at' => $requestedAt,
        ]);
    }

    public function approve(AttendanceCorrectionRequest $request, User $reviewer, ?string $notes = null): AttendanceCorrectionRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'correction_request' => ['Only pending correction requests can be reviewed.'],
            ]);
        }

        DB::transaction(function () use ($request, $reviewer, $notes): void {
            $requestedAt = Carbon::parse($request->requested_check_in_at);

            if ($request->attendanceLog) {
                $log = $request->attendanceLog;
                $log->forceFill([
                    'checked_in_at' => $requestedAt,
                    'notes' => trim(implode("\n", array_filter([
                        $log->notes,
                        'Correction approved: '.($notes ?: $request->reason),
                    ]))),
                    'check_in_method' => $log->check_in_method ?: AttendanceCheckInMethod::Manual->value,
                ])->save();
            } else {
                $gym = $request->attendanceLog?->gym ?? Gym::query()->findOrFail($request->gym_id);
                $branch = $request->attendanceLog?->branch ?? Branch::query()->findOrFail($request->branch_id);
                $member = $request->member ?? User::query()->findOrFail($request->member_id);

                $log = $this->attendanceService->recordManualCheckIn(
                    gym: $gym,
                    branch: $branch,
                    member: $member,
                    checkedInBy: $reviewer,
                    notes: trim(implode("\n", array_filter([
                        'Created from approved attendance correction request.',
                        $request->reason,
                        $notes,
                    ]))),
                    sourceDevice: 'attendance-correction',
                    checkedInAt: $requestedAt,
                );

                $request->attendance_log_id = $log->id;
            }

            $request->status = 'approved';
            $request->reviewed_by = $reviewer->id;
            $request->reviewed_at = now();
            $request->save();
        });

        return $request->fresh(['attendanceLog', 'member', 'requestedByUser', 'reviewedByUser']);
    }

    public function reject(AttendanceCorrectionRequest $request, User $reviewer, ?string $notes = null): AttendanceCorrectionRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'correction_request' => ['Only pending correction requests can be reviewed.'],
            ]);
        }

        $request->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reason' => trim(implode("\n", array_filter([$request->reason, $notes ? 'Review note: '.$notes : null]))),
        ])->save();

        return $request->fresh(['attendanceLog', 'member', 'requestedByUser', 'reviewedByUser']);
    }
}
