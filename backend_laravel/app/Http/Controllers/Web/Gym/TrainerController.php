<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\Admin\AssignTrainerMembersRequest;
use App\Http\Requests\Web\Gym\StoreTrainerWebRequest;
use App\Http\Requests\Web\Gym\UpdateTrainerWebRequest;
use App\Models\ActivityLog;
use App\Models\AttendanceLog;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\AuditTimelineService;
use App\Services\Gym\TrainerManagementService;
use App\Services\Users\ManagedUserService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrainerController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly ManagedUserService $managedUserService,
        private readonly AuditLogService $auditLogService,
        private readonly AuditTimelineService $auditTimelineService,
        private readonly TrainerManagementService $trainerManagementService,
    ) {
    }

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrainersView->value, $gym);

        $query = $this->trainerManagementService
            ->baseTrainerQuery($request, $gym, $this->gymWebPanelService);

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $hasPhoneColumn = $this->trainerManagementService->hasPhoneColumn();

            $query->where(function (Builder $builder) use ($search, $hasPhoneColumn): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);

                if ($hasPhoneColumn) {
                    $builder->orWhere('phone', 'like', $search);
                }
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('managedTrainerProfile', fn (Builder $builder) => $builder->where('branch_id', $request->integer('branch_id')));
        }

        if ($request->filled('status')) {
            $query->whereHas('managedTrainerProfile', fn (Builder $builder) => $builder->where('status', $request->string('status')->toString()));
        }

        if ($request->filled('specialization')) {
            $specialization = $request->string('specialization')->toString();
            $query->whereHas('managedTrainerProfile', function (Builder $builder) use ($specialization): void {
                $builder->where('specialization', 'like', '%'.$specialization.'%')
                    ->orWhereJsonContains('specializations', $specialization);
            });
        }

        return view('web.gym.trainers.index', [
            'pageTitle' => 'Trainers',
            'breadcrumbs' => ['Gym', 'Trainers'],
            'gym' => $gym,
            'trainers' => $query->paginate(12)->withQueryString(),
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'hasPhoneColumn' => $this->trainerManagementService->hasPhoneColumn(),
        ]);
    }

    public function create(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrainersManage->value, $gym);

        return view('web.gym.trainers.create', [
            'pageTitle' => 'Create Trainer',
            'breadcrumbs' => ['Gym', 'Trainers', 'Create'],
            'gym' => $gym,
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'existingUsers' => $this->trainerManagementService->existingUsersQuery($gym)->limit(50)->get(),
            'hasPhoneColumn' => $this->trainerManagementService->hasPhoneColumn(),
        ]);
    }

    public function store(StoreTrainerWebRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $payload = $this->normalizedPayload($request);
        $branchId = $payload['branch_id'] ?? null;

        $this->gymWebPanelService->assertPermission($request, PermissionName::TrainersManage->value, $gym, $branchId);
        $this->ensureBranchWithinScope($request, $gym, $branchId);

        $existingUser = isset($payload['existing_user_id']) ? User::query()->find($payload['existing_user_id']) : null;
        $user = $this->managedUserService->upsertTrainer($existingUser, $gym, $payload);

        $this->auditLogService->log(
            event: 'web.gym.trainer.created',
            action: 'create',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $user->managedTrainerProfile?->branch,
            newValues: $user->fresh(['managedTrainerProfile', 'branches', 'roles'])->toArray(),
        );

        return redirect()
            ->route('web.gym.trainers.show', ['trainer' => $user->id, 'gym' => $gym->id])
            ->with('status', 'Trainer created successfully.');
    }

    public function show(Request $request, User $trainer): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrainersView->value, $gym);
        $profile = $this->trainerManagementService->assertTrainerAccessible($request, $gym, $trainer, $this->gymWebPanelService);

        $trainer->load(['managedTrainerProfile.branch', 'assignedMembers.user']);

        $assignedMembers = $trainer->assignedMembers()
            ->with('user')
            ->where('gym_id', $gym->id)
            ->when($profile->branch_id, fn (Builder $builder) => $builder->where('branch_id', $profile->branch_id))
            ->latest('id')
            ->get();

        $assignedMemberIds = $assignedMembers->pluck('user_id')->filter()->values();
        $monthlyAttendanceCount = AttendanceLog::query()
            ->where('gym_id', $gym->id)
            ->whereIn('member_id', $assignedMemberIds)
            ->whereBetween('checked_in_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return view('web.gym.trainers.show', [
            'pageTitle' => $trainer->name,
            'breadcrumbs' => ['Gym', 'Trainers', $trainer->name],
            'gym' => $gym,
            'trainer' => $trainer,
            'trainerProfile' => $profile,
            'assignedMembers' => $assignedMembers,
            'recentAssignedMembers' => $assignedMembers->take(6),
            'performanceSnapshot' => [
                'monthly_attendance_count' => $monthlyAttendanceCount,
                'members_with_attendance' => AttendanceLog::query()
                    ->where('gym_id', $gym->id)
                    ->whereIn('member_id', $assignedMemberIds)
                    ->whereBetween('checked_in_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->distinct('member_id')
                    ->count('member_id'),
                'recent_activity_count' => ActivityLog::query()
                    ->where('gym_id', $gym->id)
                    ->where(function (Builder $builder) use ($trainer): void {
                        $builder->where('actor_user_id', $trainer->id)
                            ->orWhere(function (Builder $nested) use ($trainer): void {
                                $nested->where('subject_type', $trainer->getMorphClass())
                                    ->where('subject_id', $trainer->id);
                            });
                    })
                    ->where('occurred_at', '>=', now()->subDays(30))
                    ->count(),
            ],
            'assignableMembers' => $this->trainerManagementService->assignableMembers($request, $gym, $profile, $this->gymWebPanelService),
            'activityTimeline' => $this->auditTimelineService->forActivityLogs(
                ActivityLog::query()
                    ->where('gym_id', $gym->id)
                    ->where(function (Builder $builder) use ($trainer): void {
                        $builder->where('actor_user_id', $trainer->id)
                            ->orWhere(function (Builder $nested) use ($trainer): void {
                                $nested->where('subject_type', $trainer->getMorphClass())
                                    ->where('subject_id', $trainer->id);
                            });
                    })
                    ->latest('occurred_at')
                    ->take(12)
                    ->get()
            ),
            'canManageTrainer' => $this->gymWebPanelService->canPermission($request, PermissionName::TrainersManage->value, $gym, $profile->branch_id),
        ]);
    }

    public function edit(Request $request, User $trainer): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $profile = $this->trainerManagementService->assertTrainerAccessible($request, $gym, $trainer, $this->gymWebPanelService);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrainersManage->value, $gym, $profile->branch_id);

        return view('web.gym.trainers.edit', [
            'pageTitle' => 'Edit Trainer',
            'breadcrumbs' => ['Gym', 'Trainers', $trainer->name, 'Edit'],
            'gym' => $gym,
            'trainer' => $trainer->load(['managedTrainerProfile.branch', 'assignedMembers.user']),
            'trainerProfile' => $profile,
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'assignableMembers' => $this->trainerManagementService->assignableMembers($request, $gym, $profile, $this->gymWebPanelService),
            'hasPhoneColumn' => $this->trainerManagementService->hasPhoneColumn(),
        ]);
    }

    public function update(UpdateTrainerWebRequest $request, User $trainer): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $profile = $this->trainerManagementService->assertTrainerAccessible($request, $gym, $trainer, $this->gymWebPanelService);
        $payload = $this->normalizedPayload($request, $trainer, $profile);
        $branchId = $payload['branch_id'] ?? $profile->branch_id;

        $this->gymWebPanelService->assertPermission($request, PermissionName::TrainersManage->value, $gym, $branchId);
        $this->ensureBranchWithinScope($request, $gym, $branchId);

        $oldValues = $trainer->load(['managedTrainerProfile', 'branches', 'roles'])->toArray();
        $user = $this->managedUserService->upsertTrainer($trainer, $gym, $payload);

        $this->auditLogService->log(
            event: 'web.gym.trainer.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $user->managedTrainerProfile?->branch,
            oldValues: $oldValues,
            newValues: $user->fresh(['managedTrainerProfile', 'branches', 'roles'])->toArray(),
        );

        return redirect()
            ->route('web.gym.trainers.show', ['trainer' => $user->id, 'gym' => $gym->id])
            ->with('status', 'Trainer updated successfully.');
    }

    public function activate(Request $request, User $trainer): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $profile = $this->trainerManagementService->assertTrainerAccessible($request, $gym, $trainer, $this->gymWebPanelService);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrainersManage->value, $gym, $profile->branch_id);

        $oldValues = ['is_active' => $trainer->is_active];
        $user = $this->managedUserService->setTrainerActive($trainer, $gym, true);

        $this->auditLogService->log(
            event: 'web.gym.trainer.status.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: ['is_active' => $user->is_active],
        );

        return back()->with('status', 'Trainer activated successfully.');
    }

    public function deactivate(Request $request, User $trainer): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $profile = $this->trainerManagementService->assertTrainerAccessible($request, $gym, $trainer, $this->gymWebPanelService);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrainersManage->value, $gym, $profile->branch_id);

        $oldValues = ['is_active' => $trainer->is_active];
        $user = $this->managedUserService->setTrainerActive($trainer, $gym, false);

        $this->auditLogService->log(
            event: 'web.gym.trainer.status.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: ['is_active' => $user->is_active],
        );

        return back()->with('status', 'Trainer deactivated successfully.');
    }

    public function assignMembers(AssignTrainerMembersRequest $request, User $trainer): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $profile = $this->trainerManagementService->assertTrainerAccessible($request, $gym, $trainer, $this->gymWebPanelService);
        $this->gymWebPanelService->assertPermission($request, PermissionName::TrainersManage->value, $gym, $profile->branch_id);

        $accessibleBranchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);
        $memberIds = collect($request->validated('member_ids'))->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $profiles = $this->trainerManagementService->scopedMemberProfilesForAssignment($gym, $profile, $memberIds, $accessibleBranchIds);

        abort_unless($profiles->count() === count($memberIds), 422);

        DB::transaction(function () use ($profiles, $trainer, $request, $gym, $profile): void {
            foreach ($profiles as $memberProfile) {
                $oldValues = $memberProfile->toArray();
                $memberProfile->update([
                    'assigned_trainer_user_id' => $trainer->id,
                    'assigned_trainer_id' => $trainer->id,
                ]);

                $this->auditLogService->log(
                    event: 'web.gym.member.updated',
                    action: 'update',
                    request: $request,
                    subject: $memberProfile->user,
                    gym: $gym,
                    branch: $profile->branch,
                    oldValues: ['member_profile' => $oldValues],
                    newValues: ['member_profile' => $memberProfile->fresh()->toArray()],
                );
            }
        });

        $this->auditLogService->log(
            event: 'web.gym.trainer.members.assigned',
            action: 'update',
            request: $request,
            subject: $trainer,
            gym: $gym,
            branch: $profile->branch,
            newValues: ['member_ids' => $memberIds],
        );

        return back()->with('status', 'Members assigned to trainer successfully.');
    }

    public function toggleActive(Request $request, User $trainer): RedirectResponse
    {
        return $trainer->is_active
            ? $this->deactivate($request, $trainer)
            : $this->activate($request, $trainer);
    }

    private function ensureBranchWithinScope(Request $request, $gym, ?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        abort_unless(in_array($branchId, $this->gymWebPanelService->accessibleBranchIds($request, $gym), true), 422);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(Request $request, ?User $trainer = null, ?TrainerProfile $profile = null): array
    {
        $payload = $request->validated();
        $existingUser = $request->filled('existing_user_id')
            ? User::query()->find($request->validated('existing_user_id'))
            : null;

        if ($existingUser) {
            $payload['name'] = $existingUser->name;
            $payload['email'] = $existingUser->email;
            $payload['avatar'] = $existingUser->avatar;
            if ($this->trainerManagementService->hasPhoneColumn()) {
                $payload['phone'] = $existingUser->phone;
            }
        } else {
            $payload['name'] = $request->validated('name', $trainer?->name);
            $payload['email'] = $request->validated('email', $trainer?->email);
            if ($this->trainerManagementService->hasPhoneColumn() && $request->has('phone')) {
                $payload['phone'] = $request->validated('phone');
            }
        }

        $specializations = $request->validated('specializations', $profile?->specializations ?? []);
        $payload['specializations'] = $specializations;
        $payload['specialization'] = $specializations[0] ?? $request->validated('specialization', $profile?->specialization);
        $payload['branch_id'] = $request->validated('branch_id', $profile?->branch_id);
        $payload['profile_photo_url'] = $request->validated('profile_photo_url', $profile?->profile_photo_url ?? $request->validated('avatar', $trainer?->avatar));
        $payload['bio'] = $request->validated('bio', $profile?->bio);
        $payload['experience_years'] = $request->validated('experience_years', $profile?->experience_years ?? 0);
        $payload['certifications'] = $request->validated('certifications', $profile?->certifications ?? []);
        $payload['languages'] = $request->validated('languages', $profile?->languages ?? []);
        $payload['availability_notes'] = $request->validated('availability_notes', $profile?->availability_notes);
        $payload['is_active'] = $request->validated('is_active', $profile?->is_active ?? true);
        $payload['verification_status'] = $request->validated('verification_status', $profile?->verification_status ?? 'pending');

        return $payload;
    }
}
