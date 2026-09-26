<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceCheckInMethod;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceEvent;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\SmartAttendanceHub;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function biometricCheckIn(Gym $gym, Branch $branch, string $biometricIdentifier, ?User $checkedInBy, ?string $notes = null, ?string $sourceDevice = null): AttendanceLog
    {
        $profile = MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->where(function ($query) use ($branch): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branch->id);
            })
            ->where('biometric_identifier', trim($biometricIdentifier))
            ->where('biometric_enabled', true)
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'biometric_identifier' => ['No active biometric profile matched this branch scan.'],
            ]);
        }

        $member = User::query()->findOrFail((int) $profile->user_id);

        return $this->recordCheckIn(
            gym: $gym,
            branch: $branch,
            member: $member,
            checkedInBy: $checkedInBy,
            method: AttendanceCheckInMethod::Biometric->value,
            notes: $notes,
            sourceDevice: $sourceDevice,
        );
    }

    public function recordManualCheckIn(Gym $gym, Branch $branch, User $member, ?User $checkedInBy, ?string $notes = null, ?string $sourceDevice = null, mixed $checkedInAt = null): AttendanceLog
    {
        return $this->recordCheckIn(
            gym: $gym,
            branch: $branch,
            member: $member,
            checkedInBy: $checkedInBy,
            method: AttendanceCheckInMethod::Manual->value,
            notes: $notes,
            sourceDevice: $sourceDevice,
            checkedInAt: $checkedInAt,
        );
    }

    public function recordBiometricDeviceCheckIn(BiometricDevice $device, BiometricDeviceEvent $event, MemberProfile $profile, mixed $checkedInAt): AttendanceLog
    {
        return $this->recordCheckIn(
            gym: $device->gym,
            branch: $device->branch,
            member: $profile->user,
            checkedInBy: null,
            method: AttendanceCheckInMethod::Biometric->value,
            sourceDevice: $device->name,
            checkedInAt: $checkedInAt,
            biometricDevice: $device,
            biometricDeviceEvent: $event,
        );
    }

    public function recordSmartAttendanceCheckIn(Gym $gym, Branch $branch, User $member, SmartAttendanceHub $hub, array $detectionMetadata = [], mixed $detectedAt = null): AttendanceLog
    {
        return $this->recordCheckIn(
            gym: $gym,
            branch: $branch,
            member: $member,
            checkedInBy: null,
            method: AttendanceCheckInMethod::SmartAttendance->value,
            sourceDevice: 'Smart Attendance Hub '.$hub->public_id,
            checkedInAt: $detectedAt,
            smartAttendanceHub: $hub,
            smartAttendanceDetection: $detectionMetadata === [] ? null : $detectionMetadata,
        );
    }

    private function recordCheckIn(
        Gym $gym,
        Branch $branch,
        User $member,
        ?User $checkedInBy,
        string $method,
        ?string $notes = null,
        ?string $sourceDevice = null,
        mixed $checkedInAt = null,
        ?BiometricDevice $biometricDevice = null,
        ?BiometricDeviceEvent $biometricDeviceEvent = null,
        ?SmartAttendanceHub $smartAttendanceHub = null,
        ?array $smartAttendanceDetection = null,
    ): AttendanceLog {
        $checkedAt = ($checkedInAt ? Carbon::parse($checkedInAt) : now())
            ->setTimezone(config('app.timezone'));

        return DB::transaction(function () use ($gym, $branch, $member, $checkedInBy, $method, $notes, $sourceDevice, $checkedAt, $biometricDevice, $biometricDeviceEvent, $smartAttendanceHub, $smartAttendanceDetection): AttendanceLog {
            $gym = Gym::query()->findOrFail($gym->id);
            $branch = Branch::query()->findOrFail($branch->id);
            $member = User::query()->findOrFail($member->id);
            $timezone = $this->attendanceTimezone($gym, $branch);
            $localDate = $checkedAt->copy()->timezone($timezone)->toDateString();

            if ((int) $branch->gym_id !== (int) $gym->id) {
                throw ValidationException::withMessages([
                    'branch_id' => ['The selected branch does not belong to this gym.'],
                ]);
            }

            if (! $gym->is_active || $gym->status !== 'active' || ! $gym->operational_access_enabled || ! $gym->hasPlatformAccess()) {
                throw ValidationException::withMessages([
                    'gym_id' => ['Attendance is unavailable while this gym is inactive or operational access is disabled.'],
                ]);
            }

            if (! $branch->is_active || $branch->status !== 'active') {
                throw ValidationException::withMessages([
                    'branch_id' => ['Attendance is unavailable while this branch is inactive.'],
                ]);
            }

            if (! $member->is_active) {
                throw ValidationException::withMessages([
                    'member_id' => ['Attendance is unavailable while this member account is inactive.'],
                ]);
            }

            // Serializing by the member's gym profile closes the check-then-create race
            // even when no attendance row exists yet for the selected local day.
            $profile = MemberProfile::query()
                ->where('user_id', $member->id)
                ->where('gym_id', $gym->id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                throw ValidationException::withMessages([
                    'member_id' => ['The member does not belong to the selected gym.'],
                ]);
            }

            if (! $profile->is_active
                || ($profile->status !== null && $profile->status !== 'active')) {
                throw ValidationException::withMessages([
                    'member_id' => ['Attendance is unavailable because this gym member profile is inactive.'],
                ]);
            }

            if ($profile->membership_status !== 'active'
                || ($profile->membership_expires_on !== null && $profile->membership_expires_on->lt($localDate))) {
                throw ValidationException::withMessages([
                    'member_id' => ['Attendance is unavailable because the member does not have an active membership.'],
                ]);
            }

            if ($profile->branch_id !== null && (int) $profile->branch_id !== $branch->id) {
                throw ValidationException::withMessages([
                    'branch_id' => ['The member does not belong to the selected branch.'],
                ]);
            }

            $activeMembership = MemberMembership::query()
                ->where('gym_id', $gym->id)
                ->where('member_id', $member->id)
                ->where(function ($query) use ($branch): void {
                    $query->whereNull('branch_id')->orWhere('branch_id', $branch->id);
                })
                ->where('status', 'active')
                ->whereDate('start_date', '<=', $localDate)
                ->where(function ($query) use ($localDate): void {
                    $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', $localDate);
                })
                ->exists();

            if (! $activeMembership) {
                throw ValidationException::withMessages([
                    'member_id' => ['Attendance is unavailable because the member does not have an active membership.'],
                ]);
            }

            if ($method === AttendanceCheckInMethod::SmartAttendance->value && $smartAttendanceHub) {
                $existingSmartVisit = AttendanceLog::query()
                    ->where('gym_id', $gym->id)
                    ->where('branch_id', $branch->id)
                    ->where('member_id', $member->id)
                    ->where('check_in_method', AttendanceCheckInMethod::SmartAttendance->value)
                    ->where('checked_in_at', '<=', $checkedAt)
                    ->where('attendance_window_ends_at', '>', $checkedAt)
                    ->latest('checked_in_at')
                    ->lockForUpdate()
                    ->first();

                if ($existingSmartVisit) {
                    $latestPresence = $existingSmartVisit->last_presence_at;
                    if ($latestPresence === null || $checkedAt->gt($latestPresence)) {
                        $existingSmartVisit->forceFill([
                            'last_presence_at' => $checkedAt,
                            'checked_out_at' => null,
                            'smart_attendance_detection' => $this->mergeSmartDetection(
                                $existingSmartVisit->smart_attendance_detection,
                                $smartAttendanceDetection,
                            ),
                        ])->save();
                    }

                    return $existingSmartVisit->fresh();
                }
            } elseif ($gym->prevent_duplicate_same_day_checkins) {
                $localStart = Carbon::parse($localDate, $timezone)->startOfDay()->setTimezone(config('app.timezone'));
                $localEnd = Carbon::parse($localDate, $timezone)->endOfDay()->setTimezone(config('app.timezone'));
                $alreadyCheckedIn = AttendanceLog::query()
                    ->where('gym_id', $gym->id)
                    ->where('branch_id', $branch->id)
                    ->where('member_id', $member->id)
                    ->whereBetween('checked_in_at', [$localStart, $localEnd])
                    ->exists();

                if ($alreadyCheckedIn) {
                    throw ValidationException::withMessages([
                        'member_id' => ['This member has already checked in today.'],
                    ]);
                }
            }

            return AttendanceLog::query()->create([
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'member_id' => $member->id,
                'checked_in_by' => $checkedInBy?->id,
                'check_in_method' => $method,
                'checked_in_at' => $checkedAt,
                'last_presence_at' => $method === AttendanceCheckInMethod::SmartAttendance->value ? $checkedAt : null,
                'attendance_window_ends_at' => $method === AttendanceCheckInMethod::SmartAttendance->value
                    ? $checkedAt->copy()->addHours(6)
                    : null,
                'notes' => $notes,
                'source_device' => $sourceDevice ?: Str::limit((string) request()->userAgent(), 255, ''),
                'scan_reference_hash' => $biometricDeviceEvent ? hash('sha256', $biometricDevice->id.':'.$biometricDeviceEvent->payload_hash) : null,
                'biometric_device_id' => $biometricDevice?->id,
                'biometric_device_event_id' => $biometricDeviceEvent?->id,
                'smart_attendance_hub_id' => $smartAttendanceHub?->id,
                'smart_attendance_detection' => $smartAttendanceDetection,
                'occurred_at_device' => $biometricDeviceEvent?->occurred_at_device,
                'received_at' => $biometricDeviceEvent?->received_at,
            ]);
        });
    }

    public function finalizeSmartAttendanceVisit(AttendanceLog $log, User $member, mixed $lastPresenceAt = null): AttendanceLog
    {
        return DB::transaction(function () use ($log, $member, $lastPresenceAt): AttendanceLog {
            $log = AttendanceLog::query()->lockForUpdate()->findOrFail($log->id);
            if ((int) $log->member_id !== (int) $member->id
                || $log->check_in_method !== AttendanceCheckInMethod::SmartAttendance->value) {
                throw ValidationException::withMessages([
                    'attendance_log_id' => ['The Smart Attendance visit was not found.'],
                ]);
            }

            $reportedPresence = $lastPresenceAt ? Carbon::parse($lastPresenceAt)->setTimezone(config('app.timezone')) : null;
            $storedPresence = $log->last_presence_at ?: $log->checked_in_at;
            $windowEnd = $log->attendance_window_ends_at ?: $log->checked_in_at->copy()->addHours(6);
            if ($reportedPresence !== null && $storedPresence->gt($reportedPresence)) {
                return $log;
            }
            $checkoutAt = collect([$storedPresence, $reportedPresence])
                ->filter()
                ->sortByDesc(fn (Carbon $value): int => $value->getTimestamp())
                ->first() ?: $log->checked_in_at;
            if ($checkoutAt->gt($windowEnd)) {
                $checkoutAt = $windowEnd;
            }
            if ($checkoutAt->lt($log->checked_in_at)) {
                $checkoutAt = $log->checked_in_at;
            }

            $log->forceFill([
                'last_presence_at' => $checkoutAt,
                'checked_out_at' => $checkoutAt,
                'attendance_window_ends_at' => $windowEnd,
            ])->save();

            return $log->fresh();
        });
    }

    public function finalizeStaleSmartAttendanceVisits(): int
    {
        $count = 0;
        AttendanceLog::query()
            ->where('check_in_method', AttendanceCheckInMethod::SmartAttendance->value)
            ->whereNull('checked_out_at')
            ->whereNotNull('last_presence_at')
            ->where(function ($query): void {
                $query->where('last_presence_at', '<=', now()->subHours(2))
                    ->orWhere('attendance_window_ends_at', '<=', now());
            })
            ->orderBy('id')
            ->chunkById(200, function ($logs) use (&$count): void {
                foreach ($logs as $log) {
                    $finalized = DB::transaction(function () use ($log): bool {
                        $locked = AttendanceLog::query()->lockForUpdate()->find($log->id);
                        if (! $locked || $locked->checked_out_at !== null || $locked->last_presence_at === null) {
                            return false;
                        }
                        if ($locked->last_presence_at->gt(now()->subHours(2))
                            && ($locked->attendance_window_ends_at === null || $locked->attendance_window_ends_at->gt(now()))) {
                            return false;
                        }

                        $locked->forceFill(['checked_out_at' => $locked->last_presence_at])->save();

                        return true;
                    });
                    if ($finalized) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function mergeSmartDetection(?array $current, ?array $latest): ?array
    {
        if ($latest === null) {
            return $current;
        }

        return array_replace($current ?? [], [
            'last_presence' => $latest,
        ]);
    }

    private function attendanceTimezone(Gym $gym, Branch $branch): string
    {
        $timezone = $branch->timezone ?: $gym->timezone ?: config('app.timezone', 'UTC');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function localDayBounds(Gym $gym, ?Branch $branch = null, mixed $date = null): array
    {
        $timezone = $branch !== null
            ? $this->attendanceTimezone($gym, $branch)
            : ($gym->timezone ?: config('app.timezone', 'UTC'));
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'UTC';
        }

        $localDate = $date instanceof Carbon
            ? $date->format('Y-m-d')
            : ($date ?: now()->timezone($timezone)->toDateString());

        return [
            Carbon::parse($localDate, $timezone)->startOfDay()->setTimezone(config('app.timezone')),
            Carbon::parse($localDate, $timezone)->endOfDay()->setTimezone(config('app.timezone')),
        ];
    }
}
