<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\Admin\StoreBranchRequest;
use App\Http\Requests\Gym\Admin\UpdateBranchRequest;
use App\Http\Resources\Gym\BranchResource;
use App\Models\User;
use App\Models\Branch;
use App\Models\Gym;
use App\Enums\RoleName;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Gym\BranchManagementService;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly AuditLogService $auditLogService,
        private readonly BranchManagementService $branchManagementService,
    ) {
    }

    public function index(Request $request)
    {
        $gym = $this->resolveGym($request);
        $query = $gym->branches()
            ->with(['facilities', 'cityRecord'])
            ->withCount([
                'memberProfiles',
                'trainerProfiles',
                'attendanceLogs as today_check_ins_count' => fn ($query) => $query->whereDate('checked_in_at', today()),
            ])
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('city', 'like', $search)
                ->orWhere('pincode', 'like', $search));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, ['active', 'inactive'], true)) {
                $query->where('status', $status);
            }
        }

        if ($branch = $request->attributes->get('scoped_branch')) {
            $query->whereKey($branch->id);
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, BranchResource::collection($paginator->getCollection()));
    }

    public function store(StoreBranchRequest $request)
    {
        $gym = $this->resolveGym($request);
        $this->assertManageAllowed($request->user(), $gym);
        $branch = $this->branchManagementService->create($request, $gym, $request->validated());

        $this->auditLogService->log(
            event: 'gym.branch.created',
            action: 'create',
            request: $request,
            subject: $branch,
            gym: $gym,
            branch: $branch,
            newValues: $branch->fresh(['facilities'])->toArray(),
        );

        return $this->success(
            BranchResource::make($branch->fresh(['facilities', 'cityRecord'])),
            'Branch created successfully.',
            201,
        );
    }

    public function show(Request $request, Branch $branch)
    {
        $gym = $this->resolveGym($request);
        abort_unless($branch->gym_id === $gym->id, 404);
        $this->authorize('view', $branch);

        return $this->success(BranchResource::make($branch->load(['facilities', 'cityRecord'])->loadCount([
            'memberProfiles',
            'trainerProfiles',
            'attendanceLogs as today_check_ins_count' => fn ($query) => $query->whereDate('checked_in_at', today()),
        ])));
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $gym = $this->resolveGym($request);
        abort_unless($branch->gym_id === $gym->id, 404);
        $this->assertManageAllowed($request->user(), $gym, $branch);

        $oldValues = $branch->load('facilities')->toArray();
        $branch = $this->branchManagementService->update($branch, $request->validated());

        $this->auditLogService->log(
            event: 'gym.branch.updated',
            action: 'update',
            request: $request,
            subject: $branch,
            gym: $gym,
            branch: $branch,
            oldValues: $oldValues,
            newValues: $branch->fresh(['facilities'])->toArray(),
        );

        return $this->success(BranchResource::make($branch->fresh(['facilities', 'cityRecord'])));
    }

    public function destroy(Request $request, Branch $branch)
    {
        $gym = $this->resolveGym($request);
        abort_unless($branch->gym_id === $gym->id, 404);
        $this->assertManageAllowed($request->user(), $gym, $branch);

        if (! $this->branchManagementService->canDeleteSafely($branch)) {
            return $this->error(
                'This branch has active members. Deactivate it instead of deleting it.',
                422,
                ['branch' => ['Active members are assigned to this branch.']],
            );
        }

        $oldValues = $branch->toArray();
        $branch->delete();

        $this->auditLogService->log(
            event: 'gym.branch.deleted',
            action: 'delete',
            request: $request,
            subject: $branch,
            gym: $gym,
            oldValues: $oldValues,
        );

        return $this->success(null, 'Branch deleted successfully.');
    }

    public function toggleStatus(Request $request, Branch $branch)
    {
        $gym = $this->resolveGym($request);
        abort_unless($branch->gym_id === $gym->id, 404);
        $this->assertManageAllowed($request->user(), $gym, $branch);

        $oldValues = $branch->only(['is_active', 'status']);
        $branch = $this->branchManagementService->toggleStatus($branch);

        $this->auditLogService->log(
            event: 'gym.branch.status.updated',
            action: 'update',
            request: $request,
            subject: $branch,
            gym: $gym,
            branch: $branch,
            oldValues: $oldValues,
            newValues: $branch->only(['is_active', 'status']),
        );

        return $this->success(BranchResource::make($branch), 'Branch status updated successfully.');
    }

    private function resolveGym(Request $request): Gym
    {
        /** @var Gym $gym */
        $gym = $this->scopeResolver->resolveGym($request, true);

        return $gym;
    }

    private function assertManageAllowed(?User $user, Gym $gym, ?Branch $branch = null): void
    {
        abort_if(! $user, 403);
        abort_if($user->active_role === RoleName::BranchManager->value, 403);

        if ($branch) {
            $this->authorize('manage', $branch);

            return;
        }

        $this->authorize('manage', $gym);
    }
}
