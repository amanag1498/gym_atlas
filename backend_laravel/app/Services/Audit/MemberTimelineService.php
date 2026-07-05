<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\AttendanceLog;
use App\Models\CustomFeeAuditLog;
use App\Models\MemberMembership;
use App\Models\Payment;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Models\WorkoutPlan;

class MemberTimelineService
{
    public function __construct(
        private readonly AuditTimelineService $auditTimelineService,
    ) {
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     activity_logs: \Illuminate\Support\Collection<int, ActivityLog>,
     *     custom_fee_audits: \Illuminate\Support\Collection<int, CustomFeeAuditLog>,
     *     activity_timeline: list<array<string, mixed>>,
     *     status_timeline: list<array<string, mixed>>,
     *     member_timeline: list<array<string, mixed>>
     * }
     */
    public function build(User $member, int $gymId, array $branchIds): array
    {
        $membershipIds = MemberMembership::query()
            ->where('member_id', $member->id)
            ->where('gym_id', $gymId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $paymentIds = Payment::query()
            ->where('member_id', $member->id)
            ->where('gym_id', $gymId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $attendanceIds = AttendanceLog::query()
            ->where('member_id', $member->id)
            ->where('gym_id', $gymId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $workoutPlanIds = WorkoutPlan::query()
            ->where('member_id', $member->id)
            ->where('gym_id', $gymId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $progressPhotoIds = ProgressPhoto::query()
            ->where('member_id', $member->id)
            ->where('gym_id', $gymId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $activityLogs = ActivityLog::query()
            ->with('actor')
            ->where('gym_id', $gymId)
            ->whereIn('branch_id', $branchIds)
            ->where(function ($builder) use ($member, $membershipIds, $paymentIds, $attendanceIds, $workoutPlanIds, $progressPhotoIds): void {
                $builder->where(fn ($query) => $query
                    ->where('subject_type', User::class)
                    ->where('subject_id', $member->id));

                $this->appendSubjectClause($builder, MemberMembership::class, $membershipIds);
                $this->appendSubjectClause($builder, Payment::class, $paymentIds);
                $this->appendSubjectClause($builder, AttendanceLog::class, $attendanceIds);
                $this->appendSubjectClause($builder, WorkoutPlan::class, $workoutPlanIds);
                $this->appendSubjectClause($builder, ProgressPhoto::class, $progressPhotoIds);
            })
            ->latest('occurred_at')
            ->take(30)
            ->get();

        $customFeeAudits = CustomFeeAuditLog::query()
            ->with('changer')
            ->where('gym_id', $gymId)
            ->whereIn('branch_id', $branchIds)
            ->when(
                $membershipIds !== [],
                fn ($query) => $query->whereIn('member_membership_id', $membershipIds),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->latest('changed_at')
            ->take(18)
            ->get();

        $statusChangeLogs = $activityLogs
            ->filter(fn (ActivityLog $log) => in_array($log->event, [
                'web.gym.member.updated',
                'gym.member.updated',
            ], true))
            ->values();

        return [
            'activity_logs' => $activityLogs,
            'custom_fee_audits' => $customFeeAudits,
            'activity_timeline' => $this->auditTimelineService->forActivityLogs($activityLogs),
            'status_timeline' => $this->auditTimelineService->forActivityLogs($statusChangeLogs),
            'member_timeline' => $this->auditTimelineService->forMemberTimeline($activityLogs, $customFeeAudits),
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    private function appendSubjectClause($builder, string $subjectType, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $builder->orWhere(fn ($query) => $query
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', $ids));
    }
}
