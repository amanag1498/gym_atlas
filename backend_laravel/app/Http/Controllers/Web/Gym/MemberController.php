<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\Admin\AssignMemberTrainerRequest;
use App\Http\Requests\Gym\Admin\StoreMemberRequest;
use App\Http\Requests\Gym\Admin\UpdateMemberRequest;
use App\Http\Requests\Web\Gym\PreviewMemberImportRequest;
use App\Http\Requests\Web\Gym\StoreMemberImportRequest;
use App\Models\AttendanceLog;
use App\Models\MembershipPlan;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\Payment;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\MemberTimelineService;
use App\Services\Billing\BillingAccessService;
use App\Services\Billing\CustomFeeAuditService;
use App\Services\Billing\MemberMembershipLifecycleService;
use App\Services\Billing\MembershipEnrollmentService;
use App\Services\Member\EngagementScoreService;
use App\Services\Member\MemberAppService;
use App\Services\Members\MemberGymInvitationService;
use App\Services\Notification\ReminderService;
use App\Services\Users\ManagedUserService;
use App\Services\Web\CsvStreamService;
use App\Services\Web\GymMemberImportService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly ManagedUserService $managedUserService,
        private readonly MembershipEnrollmentService $membershipEnrollmentService,
        private readonly MemberMembershipLifecycleService $membershipLifecycleService,
        private readonly BillingAccessService $billingAccessService,
        private readonly CustomFeeAuditService $customFeeAuditService,
        private readonly ReminderService $reminderService,
        private readonly AuditLogService $auditLogService,
        private readonly MemberTimelineService $memberTimelineService,
        private readonly EngagementScoreService $engagementScoreService,
        private readonly GymMemberImportService $gymMemberImportService,
        private readonly CsvStreamService $csvStreamService,
        private readonly MemberGymInvitationService $memberGymInvitationService,
        private readonly MemberAppService $memberAppService,
    ) {
    }

    public function index(Request $request): View|StreamedResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembersView->value, $gym);
        $query = $this->memberQuery($request, $gym);

        if ($request->string('export')->toString() === 'csv') {
            return $this->exportMembers($query, $gym);
        }

        return $this->renderIndex($request, $gym, [
            'pageTitle' => 'Members',
            'breadcrumbs' => ['Gym', 'Members'],
            'members' => $query->paginate(12)->withQueryString(),
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $payload = $this->normalizedPayload($request);
        $branchId = $payload['branch_id'] ?? null;
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembersManage->value,
            PermissionName::MembershipsManage->value,
        ], $gym, $branchId);
        $this->assertBranchAndTrainerInScope($request, $gym, $branchId, $payload['assigned_trainer_user_id'] ?? null);

        $existingUser = isset($payload['existing_user_id']) ? User::query()->find($payload['existing_user_id']) : null;

        if ($existingUser) {
            if (! $existingUser->hasRole(RoleName::Member->value)) {
                throw ValidationException::withMessages([
                    'existing_user_id' => ['Only individual member users can be invited to join a gym.'],
                ]);
            }

            $invitation = $this->memberGymInvitationService->invite($request->user(), $existingUser, $gym, $payload);

            return back()->with('status', 'Membership invitation sent to '.$invitation->invited_email.'. The member must accept before they are added to this gym.');
        }

        $user = DB::transaction(function () use ($request, $gym, $existingUser, $payload): User {
            $user = $this->managedUserService->upsertMember($existingUser, $gym, $payload);
            $this->enrollMemberIfRequested($request, $gym, $user, $payload);

            return $user;
        });

        $this->auditLogService->log(
            event: 'web.gym.member.created',
            action: 'create',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $user->memberProfile?->branch,
            newValues: $user->fresh(['memberProfile'])->toArray(),
        );

        return back()->with('status', 'Member created successfully.');
    }

    public function show(Request $request, User $member): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembersView->value, $gym);
        $memberProfile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberBranchScope($request, $gym, $memberProfile);
        $branchIds = $this->gymWebPanelService->selectedBranchIds($request, $gym);
        $memberships = MemberMembership::query()
            ->with(['membershipPlan', 'payments.receiver', 'customFeeAuditLogs.changer'])
            ->where('member_id', $member->id)
            ->where('gym_id', $gym->id)
            ->currentFirst()
            ->get();
        $timeline = $this->memberTimelineService->build($member, $gym->id, $branchIds);
        $this->engagementScoreService->enrichMemberProfiles([$memberProfile]);

        return view('web.gym.members.show', [
            'pageTitle' => $member->name,
            'breadcrumbs' => ['Gym', 'Members', $member->name],
            'member' => $member->load(['memberProfile.assignedTrainer', 'attendanceLogs', 'weightLogs', 'bodyMeasurements', 'progressPhotos']),
            'memberProfile' => $memberProfile,
            'attendanceHistory' => AttendanceLog::query()->where('member_id', $member->id)->where('gym_id', $gym->id)->latest('checked_in_at')->take(12)->get(),
            'paymentHistory' => Payment::query()
                ->with(['membership.membershipPlan', 'collector', 'receipt'])
                ->where('member_id', $member->id)
                ->where('gym_id', $gym->id)
                ->latest('paid_at')
                ->take(12)
                ->get(),
            'membershipHistory' => $memberships,
            'workoutSummary' => [
                'total_sessions' => WorkoutSession::query()->where('member_id', $member->id)->where('gym_id', $gym->id)->count(),
            ],
            'memberAuditTimeline' => $timeline['activity_timeline'],
            'memberStatusTimeline' => $timeline['status_timeline'],
            'membershipTimeline' => $timeline['member_timeline'],
            'attendanceCorrectionTimeline' => [],
            'canManageMemberships' => $this->gymWebPanelService->canPermission($request, PermissionName::MembershipsManage->value, $gym, $memberProfile->branch_id),
            'canCollectPayments' => $this->gymWebPanelService->canPermission($request, PermissionName::PaymentsManage->value, $gym, $memberProfile->branch_id),
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'trainers' => User::query()
                ->whereHas('managedTrainerProfile', fn ($builder) => $builder
                    ->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $this->gymWebPanelService->accessibleBranchIds($request, $gym)))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembersManage->value,
            PermissionName::MembershipsManage->value,
        ], $gym);

        return view('web.gym.members.create', [
            'pageTitle' => 'Create Member',
            'breadcrumbs' => ['Gym', 'Members', 'Create'],
            'gym' => $gym,
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'trainers' => $this->trainers($request, $gym),
            'plans' => $this->plans($gym, $this->gymWebPanelService->accessibleBranchIds($request, $gym)),
            'hasPhoneColumn' => Schema::hasColumn('users', 'phone'),
            'existingUsers' => User::query()
                ->with('memberProfile')
                ->role(RoleName::Member->value)
                ->where('is_active', true)
                ->whereDoesntHave('memberProfile', fn ($builder) => $builder->where('gym_id', $gym->id))
                ->orderBy('name')
                ->limit(50)
                ->get(),
        ]);
    }

    public function update(UpdateMemberRequest $request, User $member): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $memberProfile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $branchId = $request->has('branch_id') ? $request->validated('branch_id') : $memberProfile->branch_id;
        $trainerId = $request->has('assigned_trainer_user_id') ? $request->validated('assigned_trainer_user_id') : $memberProfile->assigned_trainer_user_id;

        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembersManage->value,
            PermissionName::MembershipsManage->value,
        ], $gym, $branchId);
        $this->assertMemberBranchScope($request, $gym, $memberProfile);
        $this->assertBranchAndTrainerInScope($request, $gym, $branchId, $trainerId);

        $payload = $this->normalizedPayload($request, $member, $memberProfile);

        $oldValues = $member->load('memberProfile')->toArray();
        $user = $this->managedUserService->upsertMember($member, $gym, $payload);

        $this->auditLogService->log(
            event: 'web.gym.member.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $user->memberProfile?->branch,
            oldValues: $oldValues,
            newValues: $user->fresh(['memberProfile'])->toArray(),
        );

        return back()->with('status', 'Member updated successfully.');
    }

    public function edit(Request $request, User $member): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembersManage->value,
            PermissionName::MembershipsManage->value,
        ], $gym);
        $memberProfile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberBranchScope($request, $gym, $memberProfile);

        return view('web.gym.members.edit', [
            'pageTitle' => 'Edit Member',
            'breadcrumbs' => ['Gym', 'Members', $member->name, 'Edit'],
            'gym' => $gym,
            'member' => $member->load('memberProfile'),
            'memberProfile' => $memberProfile,
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'trainers' => $this->trainers($request, $gym),
            'hasPhoneColumn' => Schema::hasColumn('users', 'phone'),
        ]);
    }

    public function activate(Request $request, User $member): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembersManage->value, $gym);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberBranchScope($request, $gym, $profile);

        $oldValues = ['is_active' => $member->is_active];
        $user = $this->managedUserService->setMemberActive($member, $gym, true);

        $this->auditLogService->log(
            event: 'web.gym.member.status.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: ['is_active' => $user->is_active],
        );

        return back()->with('status', 'Member activated successfully.');
    }

    public function deactivate(Request $request, User $member): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembersManage->value, $gym);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberBranchScope($request, $gym, $profile);

        $oldValues = ['is_active' => $member->is_active];
        $user = $this->managedUserService->setMemberActive($member, $gym, false);

        $this->auditLogService->log(
            event: 'web.gym.member.status.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: ['is_active' => $user->is_active],
        );

        return back()->with('status', 'Member deactivated successfully.');
    }

    public function removeFromGym(Request $request, User $member): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembersManage->value, $gym);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberBranchScope($request, $gym, $profile);

        $oldValues = $member->load(['gyms', 'branches', 'roles', 'permissions'])->toArray();
        $result = $this->memberAppService->removeFromGym($member, $gym);

        $this->auditLogService->log(
            event: 'web.gym.member.removed_from_gym',
            action: 'update',
            request: $request,
            subject: $member,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: $result,
        );

        return redirect()
            ->route('web.gym.members.index', $request->only(['gym', 'branch']))
            ->with('status', 'Member removed from this gym safely. Membership, payment, attendance, and workout history remain available for audit.');
    }

    public function assignTrainer(AssignMemberTrainerRequest $request, User $member): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembersManage->value, $gym);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberBranchScope($request, $gym, $profile);
        $trainerId = $request->validated('assigned_trainer_user_id');
        $this->assertBranchAndTrainerInScope($request, $gym, $profile->branch_id, $trainerId);

        $oldValues = $member->load('memberProfile')->toArray();
        $user = $this->managedUserService->upsertMember($member, $gym, [
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->avatar,
            'branch_id' => $profile->branch_id,
            'assigned_trainer_user_id' => $trainerId,
            'fitness_goal' => $profile->fitness_goal,
            'gender' => $profile->gender,
            'height_cm' => $profile->height_cm,
            'weight_kg' => $profile->weight_kg,
            'experience_level' => $profile->experience_level,
            'medical_notes' => $profile->medical_notes,
            'injury_notes' => $profile->injury_notes,
            'emergency_contact_name' => $profile->emergency_contact_name,
            'emergency_contact_phone' => $profile->emergency_contact_phone,
            'membership_status' => $profile->membership_status,
            'membership_expires_on' => optional($profile->membership_expires_on)->toDateString(),
            'is_active' => $profile->is_active,
        ]);

        $this->auditLogService->log(
            event: 'web.gym.member.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: $user->fresh(['memberProfile'])->toArray(),
        );

        return back()->with('status', 'Trainer assignment updated successfully.');
    }

    public function previewImport(PreviewMemberImportRequest $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembersManage->value,
            PermissionName::MembershipsManage->value,
        ], $gym);

        $branches = $this->gymWebPanelService->accessibleBranches($request, $gym)->values();
        $trainers = $this->trainers($request, $gym);
        $plans = $this->plans($gym, $branches->pluck('id')->all());
        $preview = $this->gymMemberImportService->preview(
            $request->file('members_csv'),
            $gym,
            $branches,
            $trainers,
            $plans,
        );

        $request->session()->put($this->previewSessionKey($preview['token']), [
            'gym_id' => $gym->id,
            'rows' => $preview['ready_rows'],
        ]);

        return $this->renderIndex($request, $gym, [
            'pageTitle' => 'Members',
            'breadcrumbs' => ['Gym', 'Members'],
            'members' => $this->memberQuery($request, $gym)->paginate(12)->withQueryString(),
            'importPreview' => [
                'token' => $preview['token'],
                'rows' => $preview['rows'],
                'summary' => $preview['summary'],
            ],
        ]);
    }

    public function import(StoreMemberImportRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembersManage->value,
            PermissionName::MembershipsManage->value,
        ], $gym);

        $previewToken = $request->validated('preview_token');
        $preview = $request->session()->pull($this->previewSessionKey($previewToken));

        abort_unless(($preview['gym_id'] ?? null) === $gym->id, 404);

        $importedCount = 0;
        $membershipCount = 0;
        $skippedCount = 0;

        foreach ($preview['rows'] ?? [] as $row) {
            $existing = User::query()
                ->where('email', $row['email'])
                ->whereHas('memberProfile', fn ($query) => $query->where('gym_id', $gym->id))
                ->exists();

            if ($existing) {
                $skippedCount++;
                continue;
            }

            DB::transaction(function () use ($request, $gym, $row, &$importedCount, &$membershipCount): void {
                $branchId = $row['branch_id'] ?? null;
                $trainerId = $row['assigned_trainer_user_id'] ?? null;
                $this->assertBranchAndTrainerInScope($request, $gym, $branchId, $trainerId);

                $user = $this->managedUserService->upsertMember(null, $gym, $row);
                $importedCount++;

                $this->auditLogService->log(
                    event: 'web.gym.member.imported',
                    action: 'create',
                    request: $request,
                    subject: $user,
                    gym: $gym,
                    branch: $user->memberProfile?->branch,
                    newValues: $user->fresh(['memberProfile'])->toArray(),
                );

                if (! empty($row['membership_plan_id'])) {
                    $plan = MembershipPlan::query()->findOrFail($row['membership_plan_id']);
                    $this->billingAccessService->assertPlanBelongsToScope($plan, $gym->id, $branchId);

                    $this->membershipEnrollmentService->enroll($plan, $request->user(), [
                        'gym_id' => $gym->id,
                        'branch_id' => $branchId,
                        'member_id' => $user->id,
                        'membership_plan_id' => $plan->id,
                        'start_date' => $row['start_date'],
                        'amount_paid' => 0,
                        'due_date' => $row['expiry_date'] ?? null,
                    ]);

                    $membershipCount++;
                }
            });
        }

        return redirect()
            ->route('web.gym.members.index', $request->query())
            ->with('status', "Member import complete. Imported {$importedCount} members, created {$membershipCount} memberships, skipped {$skippedCount} duplicates.");
    }

    private function renderIndex(Request $request, $gym, array $data): View
    {
        if (($data['members'] ?? null) instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $this->engagementScoreService->enrichUsers($data['members']->getCollection(), $gym->id);
        }

        return view('web.gym.members.index', $data + [
            'gym' => $gym,
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'trainers' => $this->trainers($request, $gym),
            'plans' => $this->plans($gym, $this->gymWebPanelService->accessibleBranchIds($request, $gym)),
            'importPreview' => $data['importPreview'] ?? null,
        ]);
    }

    private function memberQuery(Request $request, $gym)
    {
        $query = User::query()
            ->with(['memberProfile.branch', 'memberProfile.assignedTrainer', 'memberMemberships' => fn ($builder) => $builder->currentFirst()->limit(1)])
            ->whereHas('memberProfile', fn ($builder) => $builder->where('gym_id', $gym->id))
            ->latest('id');

        if ($branch = $this->gymWebPanelService->resolveBranch($request, $gym)) {
            $query->whereHas('memberProfile', fn ($builder) => $builder->where('branch_id', $branch->id));
        } else {
            $query->whereHas('memberProfile', fn ($builder) => $builder->whereIn('branch_id', $this->gymWebPanelService->accessibleBranchIds($request, $gym)));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $hasPhoneColumn = Schema::hasColumn('users', 'phone');
            $query->where(function ($builder) use ($search, $hasPhoneColumn): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);

                if ($hasPhoneColumn) {
                    $builder->orWhere('phone', 'like', $search);
                }
            });
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if ($status === 'due_payment') {
                $query->whereHas('memberMemberships', fn ($builder) => $builder->where('due_amount', '>', 0));
            } elseif ($status === 'overdue') {
                $query->whereHas('memberMemberships', fn ($builder) => $builder->where('payment_status', 'overdue'));
            } elseif ($status === 'expiring_soon') {
                $query->whereHas('memberProfile', fn ($builder) => $builder->whereBetween('membership_expires_on', [now()->toDateString(), now()->addDays(7)->toDateString()]));
            } else {
                $query->whereHas('memberProfile', fn ($builder) => $builder->where('membership_status', $status));
            }
        }

        if ($request->filled('trainer_id')) {
            $query->whereHas('memberProfile', fn ($builder) => $builder->where('assigned_trainer_user_id', $request->integer('trainer_id')));
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('memberProfile', fn ($builder) => $builder->where('branch_id', $request->integer('branch_id')));
        }

        if ($request->filled('plan_id')) {
            $query->whereHas('memberMemberships', fn ($builder) => $builder->where('membership_plan_id', $request->integer('plan_id')));
        }

        if ($request->filled('gender')) {
            $query->whereHas('memberProfile', fn ($builder) => $builder->where('gender', $request->string('gender')));
        }

        if ($request->filled('goal')) {
            $goal = '%'.$request->string('goal').'%';
            $query->whereHas('memberProfile', fn ($builder) => $builder->where('fitness_goal', 'like', $goal));
        }

        if ($request->boolean('no_trainer_assigned')) {
            $query->whereHas('memberProfile', fn ($builder) => $builder->whereNull('assigned_trainer_user_id'));
        }

        if ($request->boolean('inactive_7_days')) {
            $query->whereDoesntHave('attendanceLogs', fn ($builder) => $builder
                ->where('gym_id', $gym->id)
                ->where('checked_in_at', '>=', now()->subDays(7)));
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(Request $request, ?User $member = null, ?MemberProfile $memberProfile = null): array
    {
        $payload = $request->validated();
        $existingUser = $request->filled('existing_user_id')
            ? User::query()->find($request->validated('existing_user_id'))
            : null;
        $profileSource = $existingUser?->memberProfile ?: $memberProfile;

        if ($existingUser) {
            $payload['name'] = $existingUser->name;
            $payload['email'] = $existingUser->email;
            if (Schema::hasColumn('users', 'phone')) {
                $payload['phone'] = $existingUser->phone;
            }
        } else {
            $payload['name'] = $request->validated('name', $member?->name);
            $payload['email'] = $request->validated('email', $member?->email);
            if (Schema::hasColumn('users', 'phone') && $request->has('phone')) {
                $payload['phone'] = $request->validated('phone');
            }
        }

        $payload['avatar'] = $request->validated('avatar', $member?->avatar);
        $payload['branch_id'] = $request->validated('branch_id', $memberProfile?->branch_id);
        $payload['assigned_trainer_user_id'] = $request->has('assigned_trainer_user_id')
            ? $request->validated('assigned_trainer_user_id')
            : $memberProfile?->assigned_trainer_user_id;
        $payload['fitness_goal'] = $request->validated('fitness_goal', $profileSource?->fitness_goal);
        $payload['gender'] = $request->validated('gender', $profileSource?->gender);
        $payload['height_cm'] = $request->validated('height_cm', $profileSource?->height_cm);
        $payload['weight_kg'] = $request->validated('weight_kg', $profileSource?->weight_kg);
        $payload['experience_level'] = $request->validated('experience_level', $profileSource?->experience_level);
        $payload['medical_notes'] = $request->validated('medical_notes', $profileSource?->medical_notes);
        $payload['injury_notes'] = $request->validated('injury_notes', $profileSource?->injury_notes);
        $payload['emergency_contact_name'] = $request->validated('emergency_contact_name', $profileSource?->emergency_contact_name);
        $payload['emergency_contact_phone'] = $request->validated('emergency_contact_phone', $profileSource?->emergency_contact_phone);
        $payload['biometric_identifier'] = $request->validated('biometric_identifier', $profileSource?->biometric_identifier);
        $payload['biometric_enabled'] = $request->validated('biometric_enabled', $profileSource?->biometric_enabled ?? false);
        $payload['membership_status'] = $request->validated('membership_status', $memberProfile?->membership_status ?? 'active');
        $payload['membership_expires_on'] = $request->validated('membership_expires_on', $memberProfile?->membership_expires_on?->toDateString());
        $payload['is_active'] = $request->validated('is_active', $memberProfile?->is_active ?? true);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enrollMemberIfRequested(Request $request, $gym, User $member, array $payload): ?MemberMembership
    {
        if (empty($payload['membership_plan_id'])) {
            return null;
        }

        $branchId = (int) ($payload['branch_id'] ?? 0);
        $plan = MembershipPlan::query()->findOrFail($payload['membership_plan_id']);
        $this->billingAccessService->assertPlanBelongsToScope($plan, $gym->id, $branchId);

        ['membership' => $membership, 'initial_payment' => $initialPayment] = $this->membershipEnrollmentService->enroll(
            $plan,
            $request->user(),
            [
                ...$payload,
                'gym_id' => $gym->id,
                'branch_id' => $branchId,
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'start_date' => $payload['start_date'] ?? now()->toDateString(),
                'due_date' => $payload['due_date'] ?? ($payload['expiry_date'] ?? null),
            ],
        );

        $this->membershipLifecycleService->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));

        if ($membership->custom_fee_enabled) {
            $this->customFeeAuditService->log(
                $membership,
                $request->user(),
                [],
                $membership->only([
                    'custom_fee_enabled',
                    'custom_fee_amount',
                    'discount_type',
                    'discount_amount',
                    'custom_joining_fee',
                    'joining_fee_waived',
                    'partial_month_fee',
                    'pt_custom_fee',
                    'final_payable_amount',
                    'due_amount',
                    'due_date',
                ]),
                $membership->custom_fee_reason ?? 'Initial custom fee applied during member creation.',
            );
        }

        $this->auditLogService->log(
            event: 'web.gym.membership.created',
            action: 'create',
            request: $request,
            subject: $membership,
            gym: $gym,
            branch: $membership->branch,
            newValues: $membership->toArray(),
        );

        if ($initialPayment) {
            $this->auditLogService->log(
                event: 'web.gym.payment.recorded',
                action: 'create',
                request: $request,
                subject: $initialPayment,
                gym: $gym,
                branch: $membership->branch,
                newValues: $initialPayment->toArray(),
                context: ['source' => 'member_creation'],
            );
        }

        $this->reminderService->syncMembershipReminders($membership->fresh(['membershipPlan']));

        return $membership;
    }

    private function exportMembers($query, $gym): StreamedResponse
    {
        $rows = $query->get()->map(function (User $member) {
            $profile = $member->memberProfile;
            $membership = $member->memberMemberships->first();

            return [
                $member->name,
                $member->email,
                $profile?->branch?->name ?? 'N/A',
                $profile?->fitness_goal ?? '',
                $profile?->assignedTrainer?->name ?? '',
                $profile?->membership_status ?? 'active',
                optional($profile?->membership_expires_on)->format('Y-m-d') ?? '',
                $membership?->membershipPlan?->name ?? '',
                number_format((float) ($membership?->amount_paid ?? 0), 2, '.', ''),
                number_format((float) ($membership?->due_amount ?? 0), 2, '.', ''),
            ];
        });

        return $this->csvStreamService->download(
            'gym-members-'.$gym->id.'-'.now()->format('Ymd-His').'.csv',
            ['Name', 'Email', 'Branch', 'Goal', 'Assigned Trainer', 'Membership Status', 'Membership Expiry', 'Current Plan', 'Amount Paid', 'Due Amount'],
            $rows,
        );
    }

    private function trainers(Request $request, $gym)
    {
        return User::query()
            ->with('managedTrainerProfile.branch')
            ->whereHas('managedTrainerProfile', fn ($builder) => $builder
                ->where('gym_id', $gym->id)
                ->whereIn('branch_id', $this->gymWebPanelService->accessibleBranchIds($request, $gym)))
            ->orderBy('name')
            ->get();
    }

    private function plans($gym, array $branchIds)
    {
        return MembershipPlan::query()
            ->with('branch')
            ->where('gym_id', $gym->id)
            ->where('status', 'active')
            ->where(function ($query) use ($branchIds): void {
                $query->whereNull('branch_id');

                if ($branchIds !== []) {
                    $query->orWhereIn('branch_id', $branchIds);
                }
            })
            ->orderBy('name')
            ->get();
    }

    private function previewSessionKey(string $token): string
    {
        return 'gym_member_import_preview:'.$token;
    }

    private function assertMemberBranchScope(Request $request, $gym, MemberProfile $profile): void
    {
        if ($request->user()?->active_role === RoleName::GymOwner->value) {
            return;
        }

        abort_unless(in_array((int) $profile->branch_id, $this->gymWebPanelService->accessibleBranchIds($request, $gym), true), 404);
    }

    private function assertBranchAndTrainerInScope(Request $request, $gym, ?int $branchId, ?int $trainerId): void
    {
        if ($branchId !== null) {
            abort_unless(in_array($branchId, $this->gymWebPanelService->accessibleBranchIds($request, $gym), true), 422);
        }

        if ($trainerId !== null) {
            $trainerQuery = TrainerProfile::query()->where('gym_id', $gym->id)->where('user_id', $trainerId);

            if ($branchId !== null) {
                $trainerQuery->where('branch_id', $branchId);
            }

            if (! $trainerQuery->exists()) {
                throw ValidationException::withMessages([
                    'assigned_trainer_user_id' => ['The selected trainer is not assigned to the selected branch.'],
                ]);
            }
        }
    }
}
