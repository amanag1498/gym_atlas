<?php

namespace App\Services\Platform;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformGymOwnerManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function hasPhoneColumn(): bool
    {
        return Schema::hasColumn('users', 'phone');
    }

    public function query(Request $request): Builder
    {
        $query = User::query()
            ->role(RoleName::GymOwner->value)
            ->withCount([
                'ownedGyms',
                'ownedGyms as active_owned_gyms_count' => fn (Builder $builder) => $builder->where('is_active', true),
            ])
            ->with(['ownedGyms:id,owner_user_id,name,city,status,approval_status,is_active'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);

                if ($this->hasPhoneColumn()) {
                    $builder->orWhere('phone', 'like', $search);
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return $query;
    }

    /**
     * @return array{owner: User, temporary_password: string}
     */
    public function create(Request $request, array $data): array
    {
        return DB::transaction(function () use ($request, $data): array {
            $temporaryPassword = Str::password(12);

            $payload = [
                'name' => trim((string) $data['name']),
                'email' => Str::lower(trim((string) $data['email'])),
                'password' => Hash::make($temporaryPassword),
                'auth_provider' => 'platform_admin_invite',
                'is_active' => true,
            ];

            if ($this->hasPhoneColumn() && array_key_exists('phone', $data)) {
                $payload['phone'] = $data['phone'];
            }

            /** @var User $owner */
            $owner = User::query()->create($payload);
            $this->assignGymOwnerRole($owner, true);

            $this->auditLogService->log(
                event: 'platform.gym_owner.created',
                action: 'create',
                request: $request,
                subject: $owner,
                oldValues: null,
                newValues: $owner->fresh()->toArray(),
            );

            return [
                'owner' => $owner->fresh(),
                'temporary_password' => $temporaryPassword,
            ];
        });
    }

    public function update(Request $request, User $owner, array $data): User
    {
        $this->ensureGymOwner($owner);

        return DB::transaction(function () use ($request, $owner, $data): User {
            $oldValues = $owner->toArray();

            $payload = [
                'name' => trim((string) $data['name']),
                'email' => Str::lower(trim((string) $data['email'])),
            ];

            if ($this->hasPhoneColumn() && array_key_exists('phone', $data)) {
                $payload['phone'] = $data['phone'];
            }

            $owner->fill($payload)->save();
            $this->assignGymOwnerRole($owner);

            $this->auditLogService->log(
                event: 'platform.gym_owner.updated',
                action: 'update',
                request: $request,
                subject: $owner,
                oldValues: $oldValues,
                newValues: $owner->fresh()->toArray(),
            );

            return $owner->fresh();
        });
    }

    public function activate(Request $request, User $owner): User
    {
        $this->ensureGymOwner($owner);

        $oldValues = $owner->only(['is_active', 'active_role']);
        $owner->forceFill([
            'is_active' => true,
            'active_role' => $owner->active_role ?: RoleName::GymOwner->value,
        ])->save();

        $this->auditLogService->log(
            event: 'platform.gym_owner.activated',
            action: 'update',
            request: $request,
            subject: $owner,
            oldValues: $oldValues,
            newValues: $owner->only(['is_active', 'active_role']),
        );

        return $owner->fresh();
    }

    public function deactivate(Request $request, User $owner, bool $force = false): User
    {
        $this->ensureGymOwner($owner);

        $activeGymCount = $owner->ownedGyms()->where('is_active', true)->count();

        if ($activeGymCount > 0 && ! $force) {
            throw ValidationException::withMessages([
                'owner' => ["This owner still has {$activeGymCount} active gym(s). Confirm deactivation before continuing."],
            ]);
        }

        $oldValues = $owner->only(['is_active']);
        $owner->forceFill(['is_active' => false])->save();

        $this->auditLogService->log(
            event: 'platform.gym_owner.deactivated',
            action: 'update',
            request: $request,
            subject: $owner,
            oldValues: $oldValues,
            newValues: $owner->only(['is_active']),
            context: [
                'forced_with_active_gyms' => $activeGymCount > 0,
                'active_owned_gyms_count' => $activeGymCount,
            ],
        );

        return $owner->fresh();
    }

    public function loadDetail(User $owner): User
    {
        $this->ensureGymOwner($owner);

        $ownedGyms = $owner->ownedGyms()
            ->withCount(['branches', 'memberProfiles', 'trainerProfiles'])
            ->with(['currentPlatformSubscription.plan'])
            ->latest('id')
            ->get(['id', 'owner_user_id', 'name', 'city', 'status', 'approval_status', 'is_active']);

        $totalBranches = $ownedGyms->sum('branches_count');
        $totalMembers = $ownedGyms->sum('member_profiles_count');
        $totalTrainers = $ownedGyms->sum('trainer_profiles_count');

        $activityLogs = $this->activityQuery($owner, $ownedGyms->pluck('id')->all())
            ->limit(8)
            ->get();

        $owner->setRelation('ownedGyms', $ownedGyms);
        $owner->setRelation('activityLogs', $activityLogs);
        $owner->setAttribute('owned_gyms_count', $ownedGyms->count());
        $owner->setAttribute('active_owned_gyms_count', $ownedGyms->where('is_active', true)->count());
        $owner->setAttribute('total_branches_count', $totalBranches);
        $owner->setAttribute('total_members_count', $totalMembers);
        $owner->setAttribute('total_trainers_count', $totalTrainers);

        return $owner;
    }

    public function activityQuery(User $owner, ?array $ownedGymIds = null): Builder
    {
        $this->ensureGymOwner($owner);

        $gymIds = $ownedGymIds ?? $owner->ownedGyms()->pluck('id')->all();

        return ActivityLog::query()
            ->with(['actor:id,name,email', 'gym:id,name', 'branch:id,name'])
            ->where(function (Builder $builder) use ($owner, $gymIds): void {
                $builder->where('actor_user_id', $owner->id)
                    ->orWhere(function (Builder $subjectQuery) use ($owner): void {
                        $subjectQuery->where('subject_type', $owner->getMorphClass())
                            ->where('subject_id', $owner->id);
                    });

                if ($gymIds !== []) {
                    $builder->orWhereIn('gym_id', $gymIds);
                }
            })
            ->latest('occurred_at')
            ->latest('id');
    }

    public function ensureGymOwner(User $owner): void
    {
        abort_unless($owner->hasRole(RoleName::GymOwner->value), 404);
    }

    private function assignGymOwnerRole(User $owner, bool $setActiveRole = false): void
    {
        if (! $owner->hasRole(RoleName::GymOwner->value)) {
            $owner->assignRole(RoleName::GymOwner->value);
        }

        if ($setActiveRole || ! $owner->active_role || ! $owner->hasRole($owner->active_role)) {
            $owner->forceFill(['active_role' => RoleName::GymOwner->value])->save();
        }
    }
}
