<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\Admin\AssignTrainerMembersRequest;
use App\Http\Requests\Gym\Admin\StoreTrainerRequest;
use App\Http\Requests\Gym\Admin\UpdateTrainerRequest;
use App\Http\Resources\User\UserResource;
use App\Models\Gym;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Gym\TrainerManagementService;
use App\Services\Users\ManagedUserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrainerController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ManagedUserService $managedUserService,
        private readonly AuditLogService $auditLogService,
        private readonly TrainerManagementService $trainerManagementService,
    ) {
    }

    public function index(Request $request)
    {
        $gym = $this->resolveGym($request);
        $branchIds = $this->accessibleBranchIds($request, $gym);

        $query = User::query()
            ->with(['gyms', 'branches', 'roles', 'permissions', 'managedTrainerProfile.gym', 'managedTrainerProfile.branch', 'assignedMembers'])
            ->withCount([
                'assignedMembers as assigned_members_count' => fn (Builder $builder) => $builder->where('gym_id', $gym->id),
            ])
            ->whereHas('managedTrainerProfile', function (Builder $builder) use ($gym, $branchIds): void {
                $builder->where('gym_id', $gym->id)
                    ->whereIn('branch_id', $branchIds);
            })
            ->latest('id');

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

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, UserResource::collection($paginator->getCollection()));
    }

    public function store(StoreTrainerRequest $request)
    {
        $gym = $this->resolveGym($request);
        $payload = $this->normalizedPayload($request);
        $this->assertBranchWithinScope($request, $gym, $payload['branch_id'] ?? null);

        $existingUser = isset($payload['existing_user_id']) ? User::query()->find($payload['existing_user_id']) : null;
        $user = $this->managedUserService->upsertTrainer($existingUser, $gym, $payload);

        $this->auditLogService->log(
            event: 'gym.trainer.created',
            action: 'create',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $user->managedTrainerProfile?->branch,
            newValues: $user->fresh(['managedTrainerProfile', 'branches', 'roles'])->toArray(),
        );

        return $this->success(
            UserResource::make($user->load(['managedTrainerProfile.gym', 'managedTrainerProfile.branch', 'assignedMembers'])),
            'Trainer created successfully.',
            201
        );
    }

    public function show(Request $request, User $trainer)
    {
        $gym = $this->resolveGym($request);
        $this->assertTrainerAccessible($request, $gym, $trainer);

        return $this->success(
            UserResource::make($trainer->load(['gyms', 'branches', 'roles', 'permissions', 'managedTrainerProfile.gym', 'managedTrainerProfile.branch', 'assignedMembers']))
        );
    }

    public function update(UpdateTrainerRequest $request, User $trainer)
    {
        $gym = $this->resolveGym($request);
        $profile = $this->assertTrainerAccessible($request, $gym, $trainer);
        $payload = $this->normalizedPayload($request, $trainer, $profile);
        $this->assertBranchWithinScope($request, $gym, $payload['branch_id'] ?? $profile->branch_id);

        $oldValues = $trainer->load(['gyms', 'branches', 'roles', 'permissions', 'managedTrainerProfile'])->toArray();
        $user = $this->managedUserService->upsertTrainer($trainer, $gym, $payload);

        $this->auditLogService->log(
            event: 'gym.trainer.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $user->managedTrainerProfile?->branch,
            oldValues: $oldValues,
            newValues: $user->fresh(['managedTrainerProfile', 'branches', 'roles'])->toArray(),
        );

        return $this->success(
            UserResource::make($user->load(['managedTrainerProfile.gym', 'managedTrainerProfile.branch', 'assignedMembers'])),
            'Trainer updated successfully.'
        );
    }

    public function activate(Request $request, User $trainer)
    {
        $gym = $this->resolveGym($request);
        $profile = $this->assertTrainerAccessible($request, $gym, $trainer);
        $oldValues = ['is_active' => $trainer->is_active];
        $user = $this->managedUserService->setTrainerActive($trainer, $gym, true);

        $this->auditLogService->log(
            event: 'gym.trainer.status.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: ['is_active' => $user->is_active],
        );

        return $this->success(UserResource::make($user->load(['managedTrainerProfile.branch'])), 'Trainer activated successfully.');
    }

    public function deactivate(Request $request, User $trainer)
    {
        $gym = $this->resolveGym($request);
        $profile = $this->assertTrainerAccessible($request, $gym, $trainer);
        $oldValues = ['is_active' => $trainer->is_active];
        $user = $this->managedUserService->setTrainerActive($trainer, $gym, false);

        $this->auditLogService->log(
            event: 'gym.trainer.status.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: ['is_active' => $user->is_active],
        );

        return $this->success(UserResource::make($user->load(['managedTrainerProfile.branch'])), 'Trainer deactivated successfully.');
    }

    public function assignMembers(AssignTrainerMembersRequest $request, User $trainer)
    {
        $gym = $this->resolveGym($request);
        $profile = $this->assertTrainerAccessible($request, $gym, $trainer);
        $memberIds = collect($request->validated('member_ids'))->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $profiles = $this->trainerManagementService->scopedMemberProfilesForAssignment(
            $gym,
            $profile,
            $memberIds,
            $this->accessibleBranchIds($request, $gym),
        );

        abort_unless($profiles->count() === count($memberIds), 422);

        DB::transaction(function () use ($profiles, $trainer, $request, $gym, $profile): void {
            foreach ($profiles as $memberProfile) {
                $oldValues = $memberProfile->toArray();
                $memberProfile->update([
                    'assigned_trainer_user_id' => $trainer->id,
                    'assigned_trainer_id' => $trainer->id,
                ]);

                $this->auditLogService->log(
                    event: 'gym.member.updated',
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

        $trainer->load(['managedTrainerProfile.branch', 'assignedMembers']);

        return $this->success(
            UserResource::make($trainer),
            'Members assigned to trainer successfully.'
        );
    }

    public function destroy(Request $request, User $trainer)
    {
        $gym = $this->resolveGym($request);
        $this->assertTrainerAccessible($request, $gym, $trainer);

        $oldValues = $trainer->load(['gyms', 'branches', 'roles', 'permissions', 'managedTrainerProfile'])->toArray();
        $this->managedUserService->removeTrainer($trainer, $gym);

        $this->auditLogService->log(
            event: 'gym.trainer.deleted',
            action: 'delete',
            request: $request,
            subject: $trainer,
            gym: $gym,
            oldValues: $oldValues,
        );

        return $this->success(null, 'Trainer removed successfully.');
    }

    private function resolveGym(Request $request): Gym
    {
        /** @var Gym $gym */
        $gym = $this->scopeResolver->resolveGym($request, true);

        return $gym;
    }

    /**
     * @return list<int>
     */
    private function accessibleBranchIds(Request $request, Gym $gym): array
    {
        return $this->scopeResolver->branchesQuery($request->user())
            ->where('gym_id', $gym->id)
            ->pluck('branches.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function assertBranchWithinScope(Request $request, Gym $gym, ?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        abort_unless(in_array($branchId, $this->accessibleBranchIds($request, $gym), true), 422);
    }

    private function assertTrainerAccessible(Request $request, Gym $gym, User $trainer): TrainerProfile
    {
        $profile = TrainerProfile::query()
            ->where('user_id', $trainer->id)
            ->where('gym_id', $gym->id)
            ->firstOrFail();

        if ($profile->branch_id !== null) {
            abort_unless(in_array((int) $profile->branch_id, $this->accessibleBranchIds($request, $gym), true), 403);
        }

        return $profile;
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
