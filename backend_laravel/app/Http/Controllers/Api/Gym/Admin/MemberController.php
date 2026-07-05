<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\Admin\AssignMemberTrainerRequest;
use App\Http\Requests\Gym\Admin\StoreMemberRequest;
use App\Http\Requests\Gym\Admin\UpdateMemberRequest;
use App\Http\Resources\Audit\ActivityLogResource;
use App\Http\Resources\User\UserResource;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\MemberTimelineService;
use App\Services\Member\EngagementScoreService;
use App\Services\Member\MemberAppService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Users\ManagedUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MemberController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ManagedUserService $managedUserService,
        private readonly AuditLogService $auditLogService,
        private readonly MemberTimelineService $memberTimelineService,
        private readonly EngagementScoreService $engagementScoreService,
        private readonly MemberAppService $memberAppService,
    ) {
    }

    public function index(Request $request)
    {
        $gym = $this->resolveGym($request);
        $branchIds = $this->accessibleBranchIds($request, $gym);
        $query = User::query()
            ->with(['gyms', 'branches', 'roles', 'permissions', 'memberProfile', 'memberMemberships.membershipPlan'])
            ->whereHas('memberProfile', function ($builder) use ($gym): void {
                $builder->where('gym_id', $gym->id);
            })
            ->latest('id');

        $query->whereHas('memberProfile', fn ($builder) => $builder->whereIn('branch_id', $branchIds));

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

        if ($request->filled('branch_id')) {
            $query->whereHas('memberProfile', fn ($builder) => $builder->where('branch_id', $request->integer('branch_id')));
        }

        if ($request->filled('trainer_id')) {
            $query->whereHas('memberProfile', fn ($builder) => $builder->where('assigned_trainer_user_id', $request->integer('trainer_id')));
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
            $cutoff = now()->subDays(7);
            $query->whereDoesntHave('attendanceLogs', fn ($builder) => $builder
                ->where('gym_id', $gym->id)
                ->where('checked_in_at', '>=', $cutoff));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            match ($status) {
                'due_amount' => $query->whereHas('memberMemberships', fn ($builder) => $builder->where('due_amount', '>', 0)),
                'due_payment' => $query->whereHas('memberMemberships', fn ($builder) => $builder->where('due_amount', '>', 0)),
                'overdue' => $query->whereHas('memberMemberships', fn ($builder) => $builder->where('payment_status', 'overdue')),
                'expiring_soon' => $query->whereHas('memberProfile', fn ($builder) => $builder->whereBetween('membership_expires_on', [now()->toDateString(), now()->addDays(7)->toDateString()])),
                default => $query->whereHas('memberProfile', fn ($builder) => $builder->where('membership_status', $status)),
            };
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 15));
        $this->engagementScoreService->enrichUsers($paginator->getCollection(), $gym->id);

        return $this->paginated($paginator, UserResource::collection($paginator->getCollection()));
    }

    public function store(StoreMemberRequest $request)
    {
        $gym = $this->resolveGym($request);
        $payload = $this->normalizedPayload($request);
        $this->assertBranchAndTrainerInScope($request, $gym, $payload['branch_id'] ?? null, $payload['assigned_trainer_user_id'] ?? null);

        $existingUser = isset($payload['existing_user_id']) ? User::query()->find($payload['existing_user_id']) : null;
        $user = $this->managedUserService->upsertMember($existingUser, $gym, $payload);

        $this->auditLogService->log(
            event: 'gym.member.created',
            action: 'create',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $user->memberProfile?->branch,
            newValues: $user->toArray(),
        );

        return $this->success(UserResource::make($user), 'Member created successfully.', 201);
    }

    public function show(Request $request, User $member)
    {
        $gym = $this->resolveGym($request);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberAccessible($request, $gym, $profile);
        $branch = $this->scopeResolver->resolveBranch($request);
        $branchIds = $branch
            ? [$branch->id]
            : $this->accessibleBranchIds($request, $gym);
        $timeline = $this->memberTimelineService->build($member, $gym->id, $branchIds);
        $this->engagementScoreService->enrichUsers([$member], $gym->id);

        return $this->success(
            [
                'member' => UserResource::make($member->load(['gyms', 'branches', 'roles', 'permissions', 'memberProfile'])),
                'activity_logs' => ActivityLogResource::collection($timeline['activity_logs']),
                'activity_timeline' => $timeline['activity_timeline'],
                'member_timeline' => $timeline['member_timeline'],
            ],
        );
    }

    public function update(UpdateMemberRequest $request, User $member)
    {
        $gym = $this->resolveGym($request);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberAccessible($request, $gym, $profile);

        $member->load(['gyms', 'branches', 'roles', 'permissions', 'memberProfile']);
        $payload = $this->normalizedPayload($request, $member, $profile);
        $this->assertBranchAndTrainerInScope($request, $gym, $payload['branch_id'] ?? null, $payload['assigned_trainer_user_id'] ?? null);
        $oldValues = $member->toArray();
        $user = $this->managedUserService->upsertMember($member, $gym, $payload);

        $this->auditLogService->log(
            event: 'gym.member.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $user->memberProfile?->branch,
            oldValues: $oldValues,
            newValues: $user->toArray(),
        );

        return $this->success(UserResource::make($user));
    }

    public function activate(Request $request, User $member)
    {
        $gym = $this->resolveGym($request);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberAccessible($request, $gym, $profile);

        $oldValues = ['is_active' => $member->is_active];
        $user = $this->managedUserService->setMemberActive($member, $gym, true);

        $this->auditLogService->log(
            event: 'gym.member.status.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: ['is_active' => $user->is_active],
        );

        return $this->success(UserResource::make($user), 'Member activated successfully.');
    }

    public function deactivate(Request $request, User $member)
    {
        $gym = $this->resolveGym($request);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberAccessible($request, $gym, $profile);

        $oldValues = ['is_active' => $member->is_active];
        $user = $this->managedUserService->setMemberActive($member, $gym, false);

        $this->auditLogService->log(
            event: 'gym.member.status.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: ['is_active' => $user->is_active],
        );

        return $this->success(UserResource::make($user), 'Member deactivated successfully.');
    }

    public function assignTrainer(AssignMemberTrainerRequest $request, User $member)
    {
        $gym = $this->resolveGym($request);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertMemberAccessible($request, $gym, $profile);
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
            'membership_expires_on' => $profile->membership_expires_on?->toDateString(),
            'is_active' => $profile->is_active,
        ]);

        $this->auditLogService->log(
            event: 'gym.member.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: $user->fresh(['memberProfile'])->toArray(),
        );

        return $this->success(UserResource::make($user), 'Trainer assignment updated successfully.');
    }

    public function destroy(Request $request, User $member)
    {
        $gym = $this->resolveGym($request);
        abort_unless(MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->exists(), 404);
        $this->authorize('manage', $gym);

        $oldValues = $member->load(['gyms', 'branches', 'roles', 'permissions', 'memberProfile'])->toArray();
        $result = $this->memberAppService->removeFromGym($member, $gym);

        $this->auditLogService->log(
            event: 'gym.member.removed_from_gym',
            action: 'update',
            request: $request,
            subject: $member,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $result,
        );

        return $this->success($result, 'Member removed from this gym safely. History remains available for audit.');
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

    private function assertMemberAccessible(Request $request, Gym $gym, MemberProfile $profile): void
    {
        abort_unless(in_array((int) $profile->branch_id, $this->accessibleBranchIds($request, $gym), true), 403);
    }

    private function assertBranchAndTrainerInScope(Request $request, Gym $gym, ?int $branchId, ?int $trainerId): void
    {
        $branchIds = $this->accessibleBranchIds($request, $gym);

        if ($branchId !== null) {
            abort_unless(in_array($branchId, $branchIds, true), 422);
        }

        if ($trainerId !== null) {
            $trainerQuery = \App\Models\TrainerProfile::query()->where('gym_id', $gym->id)->where('user_id', $trainerId);

            if ($branchId !== null) {
                $trainerQuery->where('branch_id', $branchId);
            } else {
                $trainerQuery->whereIn('branch_id', $branchIds);
            }

            abort_unless($trainerQuery->exists(), 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(Request $request, ?User $member = null, ?MemberProfile $profile = null): array
    {
        $payload = $request->validated();
        $existingUser = $request->filled('existing_user_id')
            ? User::query()->find($request->validated('existing_user_id'))
            : null;

        if ($existingUser) {
            $payload['name'] = $existingUser->name;
            $payload['email'] = $existingUser->email;
            if (Schema::hasColumn('users', 'phone')) {
                $payload['phone'] = $existingUser->phone;
            }
        } else {
            $payload['name'] = $request->validated('name', $member?->name);
            $payload['email'] = $request->validated('email', $member?->email);
            if ($request->filled('password')) {
                $payload['password'] = $request->validated('password');
            }
            if (Schema::hasColumn('users', 'phone') && $request->has('phone')) {
                $payload['phone'] = $request->validated('phone');
            }
        }

        $payload['avatar'] = $request->validated('avatar', $member?->avatar);
        $payload['branch_id'] = $request->validated('branch_id', $profile?->branch_id);
        $payload['assigned_trainer_user_id'] = $request->has('assigned_trainer_user_id')
            ? $request->validated('assigned_trainer_user_id')
            : $profile?->assigned_trainer_user_id;
        $payload['fitness_goal'] = $request->validated('fitness_goal', $profile?->fitness_goal);
        $payload['gender'] = $request->validated('gender', $profile?->gender);
        $payload['height_cm'] = $request->validated('height_cm', $profile?->height_cm);
        $payload['weight_kg'] = $request->validated('weight_kg', $profile?->weight_kg);
        $payload['experience_level'] = $request->validated('experience_level', $profile?->experience_level);
        $payload['medical_notes'] = $request->validated('medical_notes', $profile?->medical_notes);
        $payload['injury_notes'] = $request->validated('injury_notes', $profile?->injury_notes);
        $payload['emergency_contact_name'] = $request->validated('emergency_contact_name', $profile?->emergency_contact_name);
        $payload['emergency_contact_phone'] = $request->validated('emergency_contact_phone', $profile?->emergency_contact_phone);
        $payload['membership_status'] = $request->validated('membership_status', $profile?->membership_status ?? 'active');
        $payload['membership_expires_on'] = $request->validated('membership_expires_on', $profile?->membership_expires_on?->toDateString());
        $payload['is_active'] = $request->validated('is_active', $profile?->is_active ?? true);

        return $payload;
    }
}
