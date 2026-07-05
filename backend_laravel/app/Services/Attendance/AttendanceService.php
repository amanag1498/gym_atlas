<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceCheckInMethod;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\User;
use Carbon\Carbon;
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

    private function recordCheckIn(
        Gym $gym,
        Branch $branch,
        User $member,
        ?User $checkedInBy,
        string $method,
        ?string $notes = null,
        ?string $sourceDevice = null,
        mixed $checkedInAt = null,
    ): AttendanceLog {
        $profile = MemberProfile::query()
            ->where('user_id', $member->id)
            ->where('gym_id', $gym->id)
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'member_id' => ['The member does not belong to the selected gym.'],
            ]);
        }

        if ($profile->branch_id !== null && (int) $profile->branch_id !== $branch->id) {
            throw ValidationException::withMessages([
                'branch_id' => ['The member does not belong to the selected branch.'],
            ]);
        }

        $checkedAt = $checkedInAt ? Carbon::parse($checkedInAt) : now();

        $activeMembership = MemberMembership::query()
            ->where('gym_id', $gym->id)
            ->where('member_id', $member->id)
            ->where(function ($query) use ($branch): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branch->id);
            })
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $checkedAt->toDateString())
            ->where(function ($query) use ($checkedAt): void {
                $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', $checkedAt->toDateString());
            })
            ->exists();

        $profileAllowsCheckIn = (bool) $profile->is_active
            && $profile->membership_status === 'active';

        if (! $activeMembership && ! $profileAllowsCheckIn) {
            throw ValidationException::withMessages([
                'member_id' => ['Attendance is unavailable because the member does not have an active membership.'],
            ]);
        }

        if ($gym->prevent_duplicate_same_day_checkins) {
            $alreadyCheckedIn = AttendanceLog::query()
                ->where('gym_id', $gym->id)
                ->where('branch_id', $branch->id)
                ->where('member_id', $member->id)
                ->whereDate('checked_in_at', $checkedAt->toDateString())
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
            'notes' => $notes,
            'source_device' => $sourceDevice ?: Str::limit((string) request()->userAgent(), 255, ''),
        ]);
    }
}
