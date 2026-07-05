<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\Admin\StoreStaffRequest;
use App\Http\Requests\Gym\Admin\UpdateStaffRequest;
use App\Http\Resources\User\UserResource;
use App\Models\Gym;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Gym\StaffManagementService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Users\ManagedUserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly ManagedUserService $managedUserService,
        private readonly AuditLogService $auditLogService,
        private readonly StaffManagementService $staffManagementService,
    ) {
    }

    public function index(Request $request)
    {
        $gym = $this->resolveGym($request);
        $branch = $this->scopeResolver->resolveBranch($request);
        $query = User::query()
            ->with(['gyms', 'branches', 'roles', 'permissions'])
            ->whereHas('gyms', fn (Builder $builder) => $builder->where('gyms.id', $gym->id))
            ->whereHas('roles', fn (Builder $builder) => $builder->whereIn('name', [
                RoleName::BranchManager->value,
                RoleName::GymStaff->value,
            ]));

        if ($branch) {
            $query->whereHas('branches', fn (Builder $builder) => $builder->where('branches.id', $branch->id));
        } else {
            $query->whereHas('branches', fn (Builder $builder) => $builder->whereIn('branches.id', $this->accessibleBranchIds($request, $gym)));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').trim().'%';
            $hasPhoneColumn = $this->staffManagementService->hasPhoneColumn();
            $query->where(function (Builder $builder) use ($search, $hasPhoneColumn): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);

                if ($hasPhoneColumn) {
                    $builder->orWhere('phone', 'like', $search);
                }
            });
        }

        $paginator = $query->latest('id')->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, UserResource::collection($paginator->getCollection()));
    }

    public function store(StoreStaffRequest $request)
    {
        $gym = $this->resolveGym($request);
        $this->authorize('manage', $gym);
        $payload = $this->normalizedPayload($request, $gym);
        $this->staffManagementService->assertRoleAssignmentAllowed($request, $payload['role']);
        $this->staffManagementService->assertPermissionGrantAllowed($request, $gym, $payload['custom_permissions'] ?? []);
        $existingUser = isset($payload['existing_user_id']) ? User::query()->find($payload['existing_user_id']) : null;
        $user = $this->managedUserService->upsertStaff($existingUser, $gym, $payload);

        $this->auditLogService->log(
            event: 'gym.staff.created',
            action: 'create',
            request: $request,
            subject: $user,
            gym: $gym,
            newValues: $user->toArray(),
        );

        return $this->success(UserResource::make($user), 'Staff created successfully.', 201);
    }

    public function show(Request $request, User $staff)
    {
        $gym = $this->resolveGym($request);
        $this->assertStaffAccessible($request, $gym, $staff);

        return $this->success(UserResource::make($staff->load(['gyms', 'branches', 'roles', 'permissions', 'activityLogs'])));
    }

    public function update(UpdateStaffRequest $request, User $staff)
    {
        $gym = $this->resolveGym($request);
        $this->authorize('manage', $gym);
        $this->assertStaffAccessible($request, $gym, $staff);

        $oldValues = $staff->load(['gyms', 'branches', 'roles', 'permissions'])->toArray();
        $payload = $this->normalizedPayload($request, $gym, $staff);
        $this->staffManagementService->assertRoleAssignmentAllowed($request, $payload['role']);
        $this->staffManagementService->assertPermissionGrantAllowed($request, $gym, $payload['custom_permissions'] ?? []);
        $user = $this->managedUserService->upsertStaff($staff, $gym, $payload);

        $this->auditLogService->log(
            event: 'gym.staff.updated',
            action: 'update',
            request: $request,
            subject: $user,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $user->toArray(),
        );

        return $this->success(UserResource::make($user));
    }

    public function activate(Request $request, User $staff)
    {
        $gym = $this->resolveGym($request);
        $this->authorize('manage', $gym);
        $this->assertStaffAccessible($request, $gym, $staff);

        $oldValues = $staff->only(['is_active']);
        $user = $this->managedUserService->setStaffActive($staff, $gym, true);

        $this->auditLogService->log(
            event: 'gym.staff.status.updated',
            action: 'update',
            request: $request,
            subject: $staff,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $user->only(['is_active']),
        );

        return $this->success(UserResource::make($user), 'Staff activated successfully.');
    }

    public function deactivate(Request $request, User $staff)
    {
        $gym = $this->resolveGym($request);
        $this->authorize('manage', $gym);
        $this->assertStaffAccessible($request, $gym, $staff);

        $oldValues = $staff->only(['is_active']);
        $user = $this->managedUserService->setStaffActive($staff, $gym, false);

        $this->auditLogService->log(
            event: 'gym.staff.status.updated',
            action: 'update',
            request: $request,
            subject: $staff,
            gym: $gym,
            oldValues: $oldValues,
            newValues: $user->only(['is_active']),
        );

        return $this->success(UserResource::make($user), 'Staff deactivated successfully.');
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
        /** @var User $user */
        $user = $request->user();

        return $this->scopeResolver->accessibleBranches($user, $gym)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function assertStaffAccessible(Request $request, Gym $gym, User $staff): void
    {
        abort_unless($staff->gyms()->where('gyms.id', $gym->id)->exists(), 404);

        if ($request->user()?->active_role === RoleName::GymOwner->value) {
            return;
        }

        $accessibleBranchIds = $this->accessibleBranchIds($request, $gym);
        $staffBranchIds = $staff->branches()->where('branches.gym_id', $gym->id)->pluck('branches.id')->map(fn ($id): int => (int) $id)->all();

        abort_unless(array_intersect($accessibleBranchIds, $staffBranchIds) !== [], 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedPayload(Request $request, Gym $gym, ?User $staff = null): array
    {
        $payload = $request->validated();
        $existingUser = $request->filled('existing_user_id')
            ? User::query()->find($request->validated('existing_user_id'))
            : null;
        $gymPivot = $staff?->gyms()->where('gyms.id', $gym->id)->first()?->pivot;

        if ($existingUser) {
            $payload['name'] = $existingUser->name;
            $payload['email'] = $existingUser->email;
            if ($this->staffManagementService->hasPhoneColumn()) {
                $payload['phone'] = $existingUser->phone;
            }
        } else {
            $payload['name'] = $request->validated('name', $staff?->name);
            $payload['email'] = $request->validated('email', $staff?->email);
            if ($request->filled('password')) {
                $payload['password'] = $request->validated('password');
            }
        }

        $payload['role'] = $request->validated('role', $staff && $staff->hasRole(RoleName::BranchManager->value)
            ? RoleName::BranchManager->value
            : RoleName::GymStaff->value);
        $payload['branch_ids'] = $request->has('branch_ids')
            ? $request->validated('branch_ids', [])
            : ($staff ? $staff->branches()->where('branches.gym_id', $gym->id)->pluck('branches.id')->all() : []);
        $payload['custom_permissions'] = $request->has('custom_permissions')
            ? $request->validated('custom_permissions', [])
            : $this->staffManagementService->decodePermissions($gymPivot?->custom_permissions);
        $payload['is_active'] = $staff?->is_active ?? true;

        return $payload;
    }
}
