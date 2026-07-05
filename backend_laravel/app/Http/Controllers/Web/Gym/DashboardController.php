<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\Payment;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Member\EngagementScoreService;
use App\Services\Onboarding\OnboardingProgressService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly OnboardingProgressService $onboardingProgressService,
        private readonly EngagementScoreService $engagementScoreService,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $gym = $this->onboardingProgressService->syncGymProgress($this->gymWebPanelService->resolveGym($request));
        $this->gymWebPanelService->assertPermission($request, PermissionName::GymDashboardView->value, $gym);
        $branchIds = $this->gymWebPanelService->selectedBranchIds($request, $gym);
        $branchScopeId = count($branchIds) === 1 ? $branchIds[0] : null;
        $visibility = $this->buildVisibility($request, $gym, $branchScopeId);

        $memberQuery = MemberProfile::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds);
        $membershipQuery = MemberMembership::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds);
        $paymentQuery = Payment::query()
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
            ->orderByDesc('assigned_members_count')
            ->get();
        $overloadedTrainers = $overloadedTrainers
            ->filter(fn (TrainerProfile $profile): bool => (int) ($profile->assigned_members_count ?? 0) > 25)
            ->take(6)
            ->values();
        $pendingCustomFeeMemberships = (clone $membershipQuery)
            ->with(['member', 'membershipPlan'])
            ->where('custom_fee_enabled', true)
            ->whereNull('approved_by_admin_id')
            ->latest('updated_at')
            ->take(6)
            ->get();
        $expiringMemberships = (clone $membershipQuery)
            ->with(['member', 'membershipPlan'])
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->orderBy('expiry_date')
            ->take(8)
            ->get();
        $waitingTrials = (clone $trialQuery)
            ->where('status', 'pending')
            ->latest('preferred_date')
            ->take(6)
            ->get();
        $dashboardProfiles = (clone $memberQuery)
            ->with(['user', 'assignedTrainer'])
            ->get();
        $this->engagementScoreService->enrichMemberProfiles($dashboardProfiles);
        $engagementCounts = $this->engagementScoreService->categoryCounts($dashboardProfiles);
        $recentMembers = (clone $memberQuery)->with(['user', 'assignedTrainer'])->latest('id')->take(8)->get();
        $inactiveMembers = (clone $inactiveMemberQuery)->with(['user', 'assignedTrainer'])->latest('updated_at')->take(8)->get();
        $membersWithoutTrainer = (clone $membersWithoutTrainerQuery)->with(['user'])->latest('id')->take(8)->get();
        $this->engagementScoreService->enrichMemberProfiles($recentMembers);
        $this->engagementScoreService->enrichMemberProfiles($inactiveMembers);
        $this->engagementScoreService->enrichMemberProfiles($membersWithoutTrainer);
        $recentPayments = (clone $paymentQuery)
            ->with(['member', 'membership.membershipPlan', 'receiver'])
            ->latest('paid_at')
            ->take(8)
            ->get();
        $recentAttendance = (clone $attendanceQuery)
            ->with(['member', 'branch', 'checkedInByUser'])
            ->latest('checked_in_at')
            ->take(8)
            ->get();
        $recentAnnouncements = Announcement::query()
            ->with(['creator', 'branch'])
            ->where('gym_id', $gym->id)
            ->where(function (Builder $query) use ($branchIds): void {
                $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->latest('send_at')
            ->take(6)
            ->get();
        $recentActivity = ActivityLog::query()
            ->with(['actor', 'branch'])
            ->where('gym_id', $gym->id)
            ->where(function (Builder $query) use ($branchIds): void {
                $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->latest('occurred_at')
            ->take(8)
            ->get();
        $branchSnapshots = Branch::query()
            ->where('gym_id', $gym->id)
            ->whereIn('id', $branchIds)
            ->withCount(['memberProfiles', 'trainerProfiles', 'trialRequests', 'attendanceLogs'])
            ->withSum('payments as recorded_payments_sum_amount', 'amount')
            ->get()
            ->map(function (Branch $branch) use ($membershipQuery, $attendanceQuery): array {
                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'members' => (int) ($branch->member_profiles_count ?? 0),
                    'trainers' => (int) ($branch->trainer_profiles_count ?? 0),
                    'trials' => (int) ($branch->trial_requests_count ?? 0),
                    'today_check_ins' => (clone $attendanceQuery)->where('branch_id', $branch->id)->whereDate('checked_in_at', now()->toDateString())->count(),
                    'pending_dues' => (float) (clone $membershipQuery)->where('branch_id', $branch->id)->sum('due_amount'),
                    'monthly_collection' => (float) ($branch->recorded_payments_sum_amount ?? 0),
                ];
            })
            ->sortByDesc('members')
            ->values();
        $trainerLoadBoard = TrainerProfile::query()
            ->with('user', 'branch')
            ->withCount([
                'assignedMembers as assigned_members_count' => fn (Builder $query): Builder => $query
                    ->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $branchIds),
                'workoutPlans as active_workout_plans_count' => fn (Builder $query): Builder => $query
                    ->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $branchIds)
                    ->where('status', 'active'),
                'assignedTrialRequests as assigned_trials_count' => fn (Builder $query): Builder => $query
                    ->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $branchIds)
                    ->where('status', 'pending'),
            ])
            ->where('gym_id', $gym->id)
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('assigned_members_count')
            ->take(8)
            ->get();
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
        $quickActions = [
            [
                'label' => 'Add Member',
                'route' => route('web.gym.members.index', request()->query()),
                'variant' => 'primary',
                'visible' => $visibility['manage_members_action'],
            ],
            [
                'label' => 'Collect Payment',
                'route' => route('web.gym.payments.index', request()->query()),
                'variant' => 'secondary',
                'visible' => $visibility['collect_payment_action'],
            ],
            [
                'label' => 'Mark Attendance',
                'route' => route('web.gym.attendance.manual', request()->query()),
                'variant' => 'secondary',
                'visible' => $visibility['manage_attendance_action'],
            ],
            [
                'label' => 'Send Announcement',
                'route' => route('web.gym.announcements.index', request()->query()),
                'variant' => 'secondary',
                'visible' => $visibility['send_announcements_action'],
            ],
            [
                'label' => 'Add Trainer',
                'route' => route('web.gym.trainers.index', request()->query()),
                'variant' => 'secondary',
                'visible' => $visibility['manage_trainers_action'],
            ],
            [
                'label' => 'Create Plan',
                'route' => route('web.gym.membership-plans.index', request()->query()),
                'variant' => 'secondary',
                'visible' => $visibility['manage_plans_action'],
            ],
        ];

        return view('web.gym.dashboard', [
            'pageTitle' => 'Gym Dashboard',
            'breadcrumbs' => ['Gym', 'Dashboard'],
            'gym' => $gym,
            'stats' => [
                'total_members' => $memberCount,
                'active_members' => (clone $memberQuery)->where('is_active', true)->count(),
                'expired_members' => (clone $memberQuery)->where('membership_status', 'expired')->count(),
                'expiring_soon' => (clone $memberQuery)
                    ->whereBetween('membership_expires_on', [now()->toDateString(), now()->addDays(7)->toDateString()])
                    ->count(),
                'today_check_ins' => (clone $attendanceQuery)->whereDate('checked_in_at', now()->toDateString())->count(),
                'pending_dues' => $pendingDuesAmount,
                'overdue_dues' => $overdueDuesAmount,
                'monthly_collection' => (float) (clone $paymentQuery)->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
                'custom_fee_members_count' => (clone $membershipQuery)->where('custom_fee_enabled', true)->count(),
                'total_trainers' => $trainerCount,
                'trainer_member_ratio' => $trainerCount > 0 ? round($memberCount / $trainerCount, 2) : null,
                'trial_requests_count' => (clone $trialQuery)->count(),
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
                'overdue_memberships' => (clone $membershipQuery)->where('payment_status', PaymentStatus::Overdue->value)->count(),
                'unpaid_memberships' => (clone $membershipQuery)->where('payment_status', PaymentStatus::Unpaid->value)->count(),
                'partial_memberships' => (clone $membershipQuery)->where('payment_status', PaymentStatus::Partial->value)->count(),
            ],
            'onboarding' => $this->onboardingProgressService->gymChecklist($gym),
            'recentMembers' => $recentMembers,
            'pendingMemberships' => (clone $membershipQuery)->with(['member', 'membershipPlan'])->where('due_amount', '>', 0)->latest('due_date')->take(8)->get(),
            'overdueMemberships' => (clone $membershipQuery)
                ->with(['member', 'membershipPlan'])
                ->where(function (Builder $query): void {
                    $query->where('payment_status', PaymentStatus::Overdue->value)
                        ->orWhere(function (Builder $nested): void {
                            $nested->whereNotNull('due_date')
                                ->whereDate('due_date', '<', now()->toDateString())
                                ->where('due_amount', '>', 0);
                        });
                })
                ->latest('due_date')
                ->take(8)
                ->get(),
            'recentTrials' => (clone $trialQuery)->latest('id')->take(6)->get(),
            'expiringMemberships' => $expiringMemberships,
            'waitingTrials' => $waitingTrials,
            'inactiveMembers' => $inactiveMembers,
            'membersWithoutTrainer' => $membersWithoutTrainer,
            'overloadedTrainers' => $overloadedTrainers,
            'pendingCustomFeeMemberships' => $pendingCustomFeeMemberships,
            'recentPayments' => $recentPayments,
            'recentAttendance' => $recentAttendance,
            'recentAnnouncements' => $recentAnnouncements,
            'recentActivity' => $recentActivity,
            'branchSnapshots' => $branchSnapshots,
            'trainerLoadBoard' => $trainerLoadBoard,
            'visibility' => $visibility,
            'quickActions' => $quickActions,
            'paymentHealth' => [
                'unpaid' => (clone $membershipQuery)->where('payment_status', PaymentStatus::Unpaid->value)->count(),
                'partial' => (clone $membershipQuery)->where('payment_status', PaymentStatus::Partial->value)->count(),
                'paid' => (clone $membershipQuery)->where('payment_status', PaymentStatus::Paid->value)->count(),
                'overdue' => (clone $membershipQuery)->where('payment_status', PaymentStatus::Overdue->value)->count(),
            ],
        ]);
    }

    private function buildVisibility(Request $request, \App\Models\Gym $gym, ?int $branchId): array
    {
        return [
            'manage_members_action' => $this->gymWebPanelService->canPermission($request, PermissionName::MembersManage->value, $gym, $branchId),
            'members_view' => $this->gymWebPanelService->canPermission($request, PermissionName::MembersView->value, $gym, $branchId),
            'billing' => $this->gymWebPanelService->canAnyPermission($request, [
                PermissionName::PaymentsView->value,
                PermissionName::PaymentsManage->value,
                PermissionName::MembershipsView->value,
                PermissionName::MembershipsManage->value,
            ], $gym, $branchId),
            'collect_payment_action' => $this->gymWebPanelService->canPermission($request, PermissionName::PaymentsManage->value, $gym, $branchId),
            'attendance' => $this->gymWebPanelService->canAnyPermission($request, [
                PermissionName::AttendanceView->value,
                PermissionName::AttendanceManage->value,
            ], $gym, $branchId),
            'manage_attendance_action' => $this->gymWebPanelService->canPermission($request, PermissionName::AttendanceManage->value, $gym, $branchId),
            'announcements' => $this->gymWebPanelService->canAnyPermission($request, [
                PermissionName::AnnouncementsView->value,
                PermissionName::AnnouncementsManage->value,
            ], $gym, $branchId),
            'send_announcements_action' => $this->gymWebPanelService->canPermission($request, PermissionName::AnnouncementsManage->value, $gym, $branchId),
            'trainers' => $this->gymWebPanelService->canAnyPermission($request, [
                PermissionName::TrainersView->value,
                PermissionName::TrainersManage->value,
            ], $gym, $branchId),
            'manage_trainers_action' => $this->gymWebPanelService->canPermission($request, PermissionName::TrainersManage->value, $gym, $branchId),
            'plans' => $this->gymWebPanelService->canAnyPermission($request, [
                PermissionName::MembershipPlansView->value,
                PermissionName::MembershipPlansManage->value,
            ], $gym, $branchId),
            'manage_plans_action' => $this->gymWebPanelService->canPermission($request, PermissionName::MembershipPlansManage->value, $gym, $branchId),
            'trials' => $this->gymWebPanelService->canAnyPermission($request, [
                PermissionName::TrialRequestsView->value,
                PermissionName::TrialRequestsManage->value,
            ], $gym, $branchId),
        ];
    }
}
