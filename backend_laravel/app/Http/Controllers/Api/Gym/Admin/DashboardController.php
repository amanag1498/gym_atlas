<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Models\AttendanceLog;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\Payment;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use App\Models\WorkoutSession;
use App\Services\Member\EngagementScoreService;
use App\Services\Onboarding\OnboardingProgressService;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Authorization\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
        private readonly OnboardingProgressService $onboardingProgressService,
        private readonly EngagementScoreService $engagementScoreService,
    ) {
    }

    public function __invoke(Request $request)
    {
        $gym = $this->scopeResolver->resolveGym($request, true);
        $gym = $this->onboardingProgressService->syncGymProgress($gym);
        $branchIds = $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id');
        $branchScopeId = $branchIds->count() === 1 ? (int) $branchIds->first() : null;
        $visibility = $this->buildVisibility($request, $gym->id, $branchScopeId);

        $memberQuery = MemberProfile::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds);
        $membershipQuery = MemberMembership::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds);
        $paymentsQuery = Payment::query()
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $branchIds)
            ->where('status', PaymentRecordStatus::Recorded->value);
        $attendanceQuery = AttendanceLog::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds);
        $trialQuery = TrialRequest::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds);
        $trainerCount = TrainerProfile::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds)->count();
        $memberCount = (clone $memberQuery)->count();
        $membersWithoutTrainerQuery = (clone $memberQuery)->whereNull('assigned_trainer_user_id');
        $inactiveMemberQuery = (clone $memberQuery)
            ->where(function (Builder $query): void {
                $query
                    ->where('is_active', false)
                    ->orWhereDoesntHave('attendanceLogs', fn (Builder $attendance): Builder => $attendance
                        ->whereDate('checked_in_at', '>=', now()->subDays(14)->toDateString()));
            });
        $overloadedTrainers = TrainerProfile::query()
            ->with('user')
            ->withCount([
                'assignedMembers as assigned_members_count' => fn (Builder $query): Builder => $query
                    ->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $branchIds),
            ])
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $branchIds)
            ->having('assigned_members_count', '>', 25)
            ->orderByDesc('assigned_members_count')
            ->take(5)
            ->get();
        $expiringMemberships = (clone $membershipQuery)
            ->with(['member', 'membershipPlan'])
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->orderBy('expiry_date')
            ->take(5)
            ->get();
        $pendingDuesMemberships = (clone $membershipQuery)
            ->with(['member', 'membershipPlan'])
            ->where('due_amount', '>', 0)
            ->orderBy('due_date')
            ->take(5)
            ->get();
        $waitingTrials = (clone $trialQuery)
            ->where('status', 'pending')
            ->latest('preferred_date')
            ->take(5)
            ->get();
        $inactiveMembers = (clone $inactiveMemberQuery)
            ->with(['user', 'assignedTrainer'])
            ->latest('updated_at')
            ->take(5)
            ->get();
        $membersWithoutTrainer = (clone $membersWithoutTrainerQuery)
            ->with(['user'])
            ->latest('id')
            ->take(5)
            ->get();
        $pendingCustomFeeReviews = (clone $membershipQuery)
            ->with(['member', 'membershipPlan'])
            ->where('custom_fee_enabled', true)
            ->whereNull('approved_by_admin_id')
            ->latest('updated_at')
            ->take(5)
            ->get();
        $dashboardProfiles = (clone $memberQuery)->with(['user', 'assignedTrainer'])->get();
        $this->engagementScoreService->enrichMemberProfiles($dashboardProfiles);
        $engagementCounts = $this->engagementScoreService->categoryCounts($dashboardProfiles);
        $this->engagementScoreService->enrichMemberProfiles($inactiveMembers);
        $this->engagementScoreService->enrichMemberProfiles($membersWithoutTrainer);
        $pendingDuesAmount = (float) (clone $membershipQuery)->whereIn('payment_status', [
            PaymentStatus::Unpaid->value,
            PaymentStatus::Partial->value,
            PaymentStatus::Overdue->value,
        ])->sum('due_amount');
        $overdueDuesAmount = (float) (clone $membershipQuery)
            ->where(function (Builder $query): void {
                $query->where('payment_status', PaymentStatus::Overdue->value)
                    ->orWhere(function (Builder $nested): void {
                        $nested->whereNotNull('due_date')
                            ->whereDate('due_date', '<', now()->toDateString())
                            ->where('due_amount', '>', 0);
                    });
            })
            ->sum('due_amount');

        return $this->success([
            'gym' => \App\Http\Resources\Gym\GymResource::make($gym),
            'visibility' => $visibility,
            'total_members' => $memberCount,
            'active_members' => (clone $memberQuery)->where('is_active', true)->count(),
            'expired_members' => (clone $memberQuery)->where('membership_status', 'expired')->count(),
            'expiring_soon' => (clone $memberQuery)->whereBetween('membership_expires_on', [now()->toDateString(), now()->addDays(7)->toDateString()])->count(),
            'today_check_ins' => (clone $attendanceQuery)->whereDate('checked_in_at', now()->toDateString())->count(),
            'pending_dues' => $pendingDuesAmount,
            'overdue_dues' => $overdueDuesAmount,
            'monthly_collection' => (float) (clone $paymentsQuery)->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'custom_fee_members_count' => (clone $membershipQuery)->where('custom_fee_enabled', true)->count(),
            'total_trainers' => $trainerCount,
            'expiring_memberships' => (clone $membershipQuery)->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()])->count(),
            'total_workout_logs' => WorkoutSession::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds)->count(),
            'trainer_member_ratio' => $trainerCount > 0 ? round($memberCount / $trainerCount, 2) : null,
            'pending_trial_requests' => (clone $trialQuery)->where('status', 'pending')->count(),
            'inactive_members_count' => (clone $inactiveMemberQuery)->count(),
            'members_without_trainer_count' => (clone $membersWithoutTrainerQuery)->count(),
            'excellent_engagement_count' => $engagementCounts['excellent'],
            'good_engagement_count' => $engagementCounts['good'],
            'needs_attention_engagement_count' => $engagementCounts['needs_attention'],
            'high_risk_engagement_count' => $engagementCounts['high_risk'],
            'overloaded_trainers_count' => $overloadedTrainers->count(),
            'pending_custom_fee_reviews' => (clone $membershipQuery)
                ->where('custom_fee_enabled', true)
                ->whereNull('approved_by_admin_id')
                ->count(),
            'expiring_memberships_list' => $expiringMemberships->map(fn (MemberMembership $membership): array => [
                'id' => $membership->id,
                'member_name' => $membership->member?->name,
                'plan_name' => $membership->membershipPlan?->name,
                'expiry_date' => $membership->expiry_date?->toDateString(),
                'due_amount' => (float) $membership->due_amount,
            ])->values(),
            'pending_dues_list' => $pendingDuesMemberships->map(fn (MemberMembership $membership): array => [
                'id' => $membership->id,
                'member_name' => $membership->member?->name,
                'plan_name' => $membership->membershipPlan?->name,
                'due_amount' => (float) $membership->due_amount,
                'due_date' => $membership->due_date?->toDateString(),
            ])->values(),
            'trial_requests_waiting_list' => $waitingTrials->map(fn (TrialRequest $trial): array => [
                'id' => $trial->id,
                'name' => $trial->name,
                'status' => $trial->status,
                'preferred_date' => $trial->preferred_date?->toDateString(),
                'preferred_time' => $trial->preferred_time ? substr((string) $trial->preferred_time, 0, 5) : null,
            ])->values(),
            'inactive_members_list' => $inactiveMembers->map(fn (MemberProfile $member): array => [
                'id' => $member->user_id,
                'name' => $member->user?->name,
                'membership_status' => $member->membership_status,
                'assigned_trainer' => $member->assignedTrainer?->name,
                'membership_expires_on' => $member->membership_expires_on?->toDateString(),
                'engagement_score' => $member->getAttribute('engagement_score'),
            ])->values(),
            'members_without_trainer_list' => $membersWithoutTrainer->map(fn (MemberProfile $member): array => [
                'id' => $member->user_id,
                'name' => $member->user?->name,
                'fitness_goal' => $member->fitness_goal,
                'engagement_score' => $member->getAttribute('engagement_score'),
            ])->values(),
            'overloaded_trainers_list' => $overloadedTrainers->map(fn (TrainerProfile $trainer): array => [
                'id' => $trainer->user_id,
                'name' => $trainer->user?->name,
                'assigned_members_count' => (int) ($trainer->assigned_members_count ?? 0),
            ])->values(),
            'pending_custom_fee_review_list' => $pendingCustomFeeReviews->map(fn (MemberMembership $membership): array => [
                'id' => $membership->id,
                'member_name' => $membership->member?->name,
                'plan_name' => $membership->membershipPlan?->name,
                'custom_fee_reason' => $membership->custom_fee_reason,
                'custom_fee_amount' => (float) $membership->custom_fee_amount,
            ])->values(),
            'onboarding' => $this->onboardingProgressService->gymChecklist($gym),
        ]);
    }

    private function buildVisibility(Request $request, int $gymId, ?int $branchId): array
    {
        $user = $request->user();

        return [
            'manage_members_action' => $this->scopedPermissionResolver->hasPermission($user, \App\Enums\PermissionName::MembersManage->value, $gymId, $branchId),
            'members_view' => $this->scopedPermissionResolver->hasPermission($user, \App\Enums\PermissionName::MembersView->value, $gymId, $branchId),
            'billing' => $this->scopedPermissionResolver->hasAnyPermission($user, [
                \App\Enums\PermissionName::PaymentsView->value,
                \App\Enums\PermissionName::PaymentsManage->value,
                \App\Enums\PermissionName::MembershipsView->value,
                \App\Enums\PermissionName::MembershipsManage->value,
            ], $gymId, $branchId),
            'collect_payment_action' => $this->scopedPermissionResolver->hasPermission($user, \App\Enums\PermissionName::PaymentsManage->value, $gymId, $branchId),
            'attendance' => $this->scopedPermissionResolver->hasAnyPermission($user, [
                \App\Enums\PermissionName::AttendanceView->value,
                \App\Enums\PermissionName::AttendanceManage->value,
            ], $gymId, $branchId),
            'manage_attendance_action' => $this->scopedPermissionResolver->hasPermission($user, \App\Enums\PermissionName::AttendanceManage->value, $gymId, $branchId),
            'announcements' => $this->scopedPermissionResolver->hasAnyPermission($user, [
                \App\Enums\PermissionName::AnnouncementsView->value,
                \App\Enums\PermissionName::AnnouncementsManage->value,
            ], $gymId, $branchId),
            'send_announcements_action' => $this->scopedPermissionResolver->hasPermission($user, \App\Enums\PermissionName::AnnouncementsManage->value, $gymId, $branchId),
            'trainers' => $this->scopedPermissionResolver->hasAnyPermission($user, [
                \App\Enums\PermissionName::TrainersView->value,
                \App\Enums\PermissionName::TrainersManage->value,
            ], $gymId, $branchId),
            'manage_trainers_action' => $this->scopedPermissionResolver->hasPermission($user, \App\Enums\PermissionName::TrainersManage->value, $gymId, $branchId),
            'plans' => $this->scopedPermissionResolver->hasAnyPermission($user, [
                \App\Enums\PermissionName::MembershipPlansView->value,
                \App\Enums\PermissionName::MembershipPlansManage->value,
            ], $gymId, $branchId),
            'manage_plans_action' => $this->scopedPermissionResolver->hasPermission($user, \App\Enums\PermissionName::MembershipPlansManage->value, $gymId, $branchId),
            'trials' => $this->scopedPermissionResolver->hasAnyPermission($user, [
                \App\Enums\PermissionName::TrialRequestsView->value,
                \App\Enums\PermissionName::TrialRequestsManage->value,
            ], $gymId, $branchId),
        ];
    }
}
