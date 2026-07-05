<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Gym;
use App\Models\User;
use App\Services\Audit\AdminActivityFeedService;
use App\Services\Audit\AuditLogService;
use App\Services\Platform\PlatformAuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly AdminActivityFeedService $adminActivityFeedService,
        private readonly PlatformAuditLogService $platformAuditLogService,
    ) {
    }

    public function index(Request $request): View
    {
        return $this->renderListing($request, null, 'Users');
    }

    public function trainers(Request $request): View
    {
        return $this->renderListing($request, RoleName::Trainer->value, 'Trainers');
    }

    public function members(Request $request): View
    {
        return $this->renderListing($request, RoleName::Member->value, 'Members');
    }

    public function show(User $user): View
    {
        $user->load([
            'roles',
            'gyms.currentPlatformSubscription.plan',
            'branches',
            'managedTrainerProfile.gym',
            'managedTrainerProfile.branch',
            'managedTrainerProfile.assignedMembers.gym',
            'managedTrainerProfile.assignedMembers.branch',
            'memberProfile.gym',
            'memberProfile.branch',
            'memberProfile.assignedTrainer',
            'memberProfile.fitnessGoals',
            'memberProfile.trainerNotes.trainer',
            'memberProfile.payments.gym',
            'memberProfile.payments.branch',
            'memberProfile.payments.membership.membershipPlan',
            'memberProfile.payments.receiver',
            'memberProfile.payments.collector',
            'memberProfile.payments.receipt',
            'memberProfile.attendanceLogs.gym',
            'memberProfile.attendanceLogs.branch',
            'memberProfile.attendanceLogs.checkedInByUser',
            'memberProfile.weightLogs.gym',
            'memberProfile.weightLogs.branch',
            'memberProfile.weightLogs.logger',
            'memberProfile.bodyMeasurements.gym',
            'memberProfile.bodyMeasurements.branch',
            'memberProfile.bodyMeasurements.logger',
            'memberProfile.progressPhotos.gym',
            'memberProfile.progressPhotos.branch',
            'memberProfile.progressPhotos.uploader',
            'memberProfile.personalRecords.exercise',
            'memberProfile.personalRecords.workoutSession',
            'memberProfile.workoutPlans.gym',
            'memberProfile.workoutPlans.branch',
            'memberProfile.workoutPlans.trainer',
            'memberProfile.workoutSessions.gym',
            'memberProfile.workoutSessions.branch',
            'memberProfile.workoutSessions.trainer',
            'ownedGyms.currentPlatformSubscription.plan',
            'staffAssignments.gym',
            'staffAssignments.branch',
            'memberMemberships.gym',
            'memberMemberships.branch',
            'memberMemberships.membershipPlan',
            'memberMemberships.payments',
            'memberMemberships.approver',
            'assignedMembers.gym',
            'dailySteps.gym',
            'scheduledReminders.gym',
            'scheduledReminders.branch',
            'scheduledReminders.membership.membershipPlan',
            'trialRequests.gym',
            'trialRequests.branch',
            'trialRequests.assignedTrainer',
            'workoutPlansAsMember.gym',
            'workoutPlansAsMember.branch',
            'workoutPlansAsMember.trainer',
            'workoutPlansAsMember.creator',
            'workoutSessionsAsMember.gym',
            'workoutSessionsAsMember.branch',
            'workoutSessionsAsMember.trainer',
            'workoutSessionsAsMember.plan',
            'workoutSessionsAsMember.starter',
        ]);
        $user->loadCount([
            'ownedGyms',
            'assignedMembers',
            'memberMemberships',
            'attendanceLogs',
            'fcmTokens',
            'staffAssignments',
            'notifications',
            'recordedPayments',
            'dailySteps',
            'trialRequests',
            'workoutPlansAsMember',
            'workoutSessionsAsMember',
        ]);

        $activityLogs = $this->userActivityQuery($user)
            ->limit(8)
            ->get();

        $activityFeed = $this->adminActivityFeedService->build($activityLogs);

        return view('web.admin.users.show', [
            'pageTitle' => $user->name,
            'breadcrumbs' => ['Platform', 'Users', $user->name],
            'userDetail' => $user,
            'activityLogs' => $activityLogs,
            'activityTimeline' => $activityFeed['timeline'],
            'activityStats' => $activityFeed['stats'],
            'activityRows' => $activityFeed['rows'],
            'activityLatestLabel' => $activityFeed['latest_label'],
            'hasPhoneColumn' => Schema::hasColumn('users', 'phone'),
        ]);
    }

    public function activity(Request $request, User $user): View
    {
        $query = $this->userActivityQuery($user);

        if ($request->filled('action')) {
            $action = '%'.$request->string('action')->trim().'%';
            $query->where(function (Builder $builder) use ($action): void {
                $builder->where('action', 'like', $action)
                    ->orWhere('event', 'like', $action);
            });
        }

        if ($request->date('start_date')) {
            $startDate = $request->date('start_date')->startOfDay();
            $query->where(function (Builder $builder) use ($startDate): void {
                $builder->where('occurred_at', '>=', $startDate)
                    ->orWhere(function (Builder $nested) use ($startDate): void {
                        $nested->whereNull('occurred_at')
                            ->where('created_at', '>=', $startDate);
                    });
            });
        }

        if ($request->date('end_date')) {
            $endDate = $request->date('end_date')->endOfDay();
            $query->where(function (Builder $builder) use ($endDate): void {
                $builder->where('occurred_at', '<=', $endDate)
                    ->orWhere(function (Builder $nested) use ($endDate): void {
                        $nested->whereNull('occurred_at')
                            ->where('created_at', '<=', $endDate);
                    });
            });
        }

        $auditLogs = $query->paginate(20)->withQueryString();

        return view('web.admin.users.activity', [
            'pageTitle' => $user->name.' Activity',
            'breadcrumbs' => ['Platform', 'Users', $user->name, 'Activity'],
            'userDetail' => $user,
            'auditLogs' => $auditLogs,
            'filters' => [
                'action' => $request->string('action')->toString(),
                'start_date' => $request->string('start_date')->toString(),
                'end_date' => $request->string('end_date')->toString(),
            ],
            'sanitizer' => $this->platformAuditLogService,
        ]);
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        if ($user->is_active) {
            return back()->with('status', 'User is already active.');
        }

        $oldValues = $user->only(['is_active']);
        $user->forceFill(['is_active' => true])->save();

        $this->auditLogService->log(
            event: 'web.platform.user.activated',
            action: 'update',
            request: $request,
            subject: $user,
            oldValues: $oldValues,
            newValues: $user->only(['is_active']),
        );

        return back()->with('status', 'User activated successfully.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors([
                'user' => 'You cannot deactivate your own platform admin account.',
            ]);
        }

        if (! $user->is_active) {
            return back()->with('status', 'User is already inactive.');
        }

        $oldValues = $user->only(['is_active']);
        $user->forceFill(['is_active' => false])->save();

        $this->auditLogService->log(
            event: 'web.platform.user.deactivated',
            action: 'update',
            request: $request,
            subject: $user,
            oldValues: $oldValues,
            newValues: $user->only(['is_active']),
        );

        return back()->with('status', 'User deactivated successfully.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        return $user->is_active
            ? $this->deactivate($request, $user)
            : $this->activate($request, $user);
    }

    private function renderListing(Request $request, ?string $role, string $title): View
    {
        $hasPhoneColumn = Schema::hasColumn('users', 'phone');
        $query = User::query()
            ->with(['gyms', 'branches', 'managedTrainerProfile', 'memberProfile', 'roles'])
            ->withCount(['ownedGyms', 'assignedMembers', 'memberMemberships'])
            ->latest('id');

        if ($role) {
            $query->whereHas('roles', fn (Builder $builder) => $builder->where('name', $role));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($search, $hasPhoneColumn): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);

                if ($hasPhoneColumn) {
                    $builder->orWhere('phone', 'like', $search);
                }
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn (Builder $builder) => $builder->where('name', $request->string('role')));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        if ($request->filled('gym_id')) {
            $gymId = $request->integer('gym_id');
            $query->where(function (Builder $builder) use ($gymId): void {
                $builder->whereHas('gyms', fn (Builder $gymQuery) => $gymQuery->where('gyms.id', $gymId))
                    ->orWhereHas('ownedGyms', fn (Builder $gymQuery) => $gymQuery->where('gyms.id', $gymId))
                    ->orWhereHas('managedTrainerProfile', fn (Builder $trainerQuery) => $trainerQuery->where('gym_id', $gymId))
                    ->orWhereHas('memberProfile', fn (Builder $memberQuery) => $memberQuery->where('gym_id', $gymId))
                    ->orWhereHas('staffAssignments', fn (Builder $staffQuery) => $staffQuery->where('gym_id', $gymId));
            });
        }

        $users = $query->paginate(20)->withQueryString();
        $roleCounts = collect([
            RoleName::PlatformAdmin->value,
            RoleName::GymOwner->value,
            RoleName::BranchManager->value,
            RoleName::GymStaff->value,
            RoleName::Trainer->value,
            RoleName::Member->value,
        ])->mapWithKeys(fn (string $roleName) => [
            $roleName => User::query()->role($roleName)->count(),
        ])->all();

        return view('web.admin.users.index', [
            'pageTitle' => $title,
            'breadcrumbs' => ['Platform', $title],
            'title' => $title,
            'users' => $users,
            'roleCounts' => $roleCounts,
            'hasPhoneColumn' => $hasPhoneColumn,
            'gyms' => Gym::query()->orderBy('name')->get(['id', 'name']),
            'selectedRole' => $role,
        ]);
    }

    private function userActivityQuery(User $user): Builder
    {
        return ActivityLog::query()
            ->with(['actor:id,name,email', 'gym:id,name', 'branch:id,name'])
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('actor_user_id', $user->id)
                    ->orWhere(function (Builder $subjectQuery) use ($user): void {
                        $subjectQuery->where('subject_type', $user->getMorphClass())
                            ->where('subject_id', $user->id);
                    });
            })
            ->latest('occurred_at')
            ->latest('id');
    }
}
