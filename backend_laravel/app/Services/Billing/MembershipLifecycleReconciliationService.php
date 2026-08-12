<?php

namespace App\Services\Billing;

use App\Models\MemberMembership;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Member\MemberAppService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MembershipLifecycleReconciliationService
{
    public function __construct(
        private readonly MemberMembershipLifecycleService $membershipLifecycleService,
        private readonly MemberAppService $memberAppService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return array{expired:int, profiles_activated:int, terminal_access_revocations:int}
     */
    public function reconcile(?CarbonInterface $asOf = null): array
    {
        $date = ($asOf ?? today())->toDateString();
        $result = [
            'expired' => 0,
            'profiles_activated' => 0,
            'terminal_access_revocations' => 0,
        ];

        MemberMembership::query()
            ->where('status', 'active')
            ->whereDate('expiry_date', '<', $date)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $membershipId) use ($date, &$result): void {
                $transition = DB::transaction(function () use ($membershipId, $date): ?array {
                    $membership = MemberMembership::query()
                        ->with(['member', 'gym', 'branch'])
                        ->lockForUpdate()
                        ->find($membershipId);

                    if (! $membership
                        || $membership->status !== 'active'
                        || ! $membership->expiry_date?->lt($date)) {
                        return null;
                    }

                    $oldValues = $membership->only(['status', 'start_date', 'expiry_date']);
                    $membership->forceFill(['status' => 'expired'])->save();
                    $this->cancelMembershipReminders($membership);

                    $hasCurrentOrFutureCycle = MemberMembership::query()
                        ->where('member_id', $membership->member_id)
                        ->where('gym_id', $membership->gym_id)
                        ->where(function ($query) use ($date): void {
                            $query->where('status', 'frozen')
                                ->orWhere(function ($active) use ($date): void {
                                    $active->where('status', 'active')
                                        ->whereDate('expiry_date', '>=', $date);
                                });
                        })
                        ->exists();

                    $member = $membership->member;
                    if ($member instanceof User) {
                        if ($hasCurrentOrFutureCycle) {
                            $this->membershipLifecycleService->syncMemberProfileSummary(
                                $member,
                                (int) $membership->gym_id,
                                $date,
                            );
                        } else {
                            $this->memberAppService->expireGymAccess(
                                $member,
                                (int) $membership->gym_id,
                                $membership,
                                $date,
                            );
                        }
                    }

                    $this->auditLogService->log(
                        event: 'membership.expired',
                        action: 'update',
                        subject: $membership,
                        gym: $membership->gym,
                        branch: $membership->branch,
                        oldValues: $oldValues,
                        newValues: $membership->fresh()->only(['status', 'start_date', 'expiry_date']),
                        context: [
                            'source' => 'daily_lifecycle_reconciliation',
                            'as_of_date' => $date,
                            'has_current_or_future_cycle' => $hasCurrentOrFutureCycle,
                        ],
                    );

                    return ['terminal' => ! $hasCurrentOrFutureCycle];
                });

                if ($transition === null) {
                    return;
                }

                $result['expired']++;
                if ($transition['terminal']) {
                    $result['terminal_access_revocations']++;
                }
            });

        // Repair profiles when a queued renewal becomes effective after one
        // or more scheduler runs were missed.
        MemberMembership::query()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $membershipId) use ($date, &$result): void {
                $activated = DB::transaction(function () use ($membershipId, $date): bool {
                    $membership = MemberMembership::query()
                        ->with('member')
                        ->lockForUpdate()
                        ->find($membershipId);

                    if (! $membership
                        || $membership->status !== 'active'
                        || $membership->start_date?->gt($date)
                        || $membership->expiry_date?->lt($date)
                        || ! $membership->member instanceof User) {
                        return false;
                    }

                    return $this->membershipLifecycleService->syncMemberProfileSummary(
                        $membership->member,
                        (int) $membership->gym_id,
                        $date,
                    );
                });

                if ($activated) {
                    $result['profiles_activated']++;
                }
            });

        return $result;
    }

    private function cancelMembershipReminders(MemberMembership $membership): void
    {
        $membership->scheduledReminders()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }
}
