<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Users\ManagedUserService;
use App\Support\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    public function __construct(private readonly ManagedUserService $managedUserService) {}

    public function index(Request $request)
    {
        $query = $this->baseQuery($request)->latest('id');
        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, UserResource::collection($paginator->getCollection()));
    }

    public function trainers(Request $request)
    {
        $query = $this->baseQuery($request)
            ->whereHas('roles', fn (Builder $builder) => $builder->where('name', RoleName::Trainer->value))
            ->latest('id');

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, UserResource::collection($paginator->getCollection()));
    }

    public function members(Request $request)
    {
        $query = $this->baseQuery($request)
            ->whereHas('roles', fn (Builder $builder) => $builder->where('name', RoleName::Member->value))
            ->latest('id');

        $paginator = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, UserResource::collection($paginator->getCollection()));
    }

    public function show(User $user)
    {
        $user->load([
            'roles',
            'permissions',
            'gyms',
            'branches',
            'managedTrainerProfile.gym',
            'managedTrainerProfile.branch',
            'memberProfile.gym',
            'memberProfile.branch',
            'memberProfiles.gym',
            'memberProfiles.branch',
            'memberProfiles.assignedTrainer',
            'memberProfiles.fitnessGoals',
            'ownedGyms',
            'staffAssignments.gym',
            'staffAssignments.branch',
        ]);

        $activityLogs = ActivityLog::query()
            ->with('gym:id,name')
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('actor_user_id', $user->id)
                    ->orWhere(function (Builder $subjectQuery) use ($user): void {
                        $subjectQuery->where('subject_type', $user->getMorphClass())
                            ->where('subject_id', $user->id);
                    });
            })
            ->latest('occurred_at')
            ->limit(10)
            ->get();

        $user->setRelation('activityLogs', $activityLogs);

        return $this->success(UserResource::make($user), 'User loaded successfully.');
    }

    public function activate(Request $request, User $user)
    {
        if ($user->is_active) {
            return $this->success(UserResource::make($user), 'User is already active.');
        }

        $oldValues = $user->only(['is_active']);
        $user = $this->managedUserService->setPlatformUserActive($user, true);

        app(AuditLogService::class)->log(
            event: 'platform.user.activated',
            action: 'update',
            request: $request,
            subject: $user,
            oldValues: $oldValues,
            newValues: $user->only(['is_active']),
        );

        return $this->success(UserResource::make($user->fresh()), 'User activated successfully.');
    }

    public function deactivate(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return ApiResponse::error('You cannot deactivate your own platform admin account.', 422);
        }

        if (! $user->is_active) {
            return $this->success(UserResource::make($user), 'User is already inactive.');
        }

        $oldValues = $user->only(['is_active']);
        $user = $this->managedUserService->setPlatformUserActive($user, false);

        app(AuditLogService::class)->log(
            event: 'platform.user.deactivated',
            action: 'update',
            request: $request,
            subject: $user,
            oldValues: $oldValues,
            newValues: $user->only(['is_active']),
        );

        return $this->success(UserResource::make($user->fresh()), 'User deactivated successfully.');
    }

    private function baseQuery(Request $request): Builder
    {
        $hasPhoneColumn = Schema::hasColumn('users', 'phone');
        $query = User::query()
            ->with([
                'gyms',
                'branches',
                'roles',
                'permissions',
                'managedTrainerProfile',
                'memberProfile',
                'memberProfiles.gym',
                'memberProfiles.branch',
                'memberProfiles.assignedTrainer',
            ])
            ->withCount(['ownedGyms']);

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
            $query->whereHas('roles', fn (Builder $builder) => $builder->where('name', $request->string('role')->toString()));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return $query;
    }
}
