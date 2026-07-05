<?php

namespace App\Services\Trials;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use App\Models\User;
use App\Services\Users\ManagedUserService;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TrialRequestService
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
        private readonly ManagedUserService $managedUserService,
    ) {
    }

    public function createPublic(array $data, ?User $actor = null, ?Request $request = null): TrialRequest
    {
        $gym = Gym::query()->findOrFail($data['gym_id']);
        $branch = null;

        if (! $gym->public_listing_enabled
            || $gym->public_listing_approval_status !== 'approved'
            || ! $gym->trial_available
            || ! $gym->is_active
            || $gym->status !== 'active'
            || $gym->approval_status === 'rejected') {
            throw ValidationException::withMessages([
                'gym_id' => ['Trial requests are not enabled for this gym.'],
            ]);
        }

        if (! empty($data['branch_id'])) {
            $branch = Branch::query()->findOrFail($data['branch_id']);

            if ((int) $branch->gym_id !== (int) $gym->id) {
                throw ValidationException::withMessages([
                    'branch_id' => ['The selected branch does not belong to the selected gym.'],
                ]);
            }
        } else {
            $activeBranches = $gym->branches()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            if ($activeBranches->isEmpty()) {
                throw ValidationException::withMessages([
                    'branch_id' => ['No active branch is available for this gym.'],
                ]);
            }

            $branch = $activeBranches->first();
        }

        return DB::transaction(function () use ($data, $actor, $request, $gym, $branch): TrialRequest {
            $requestType = in_array(($data['request_type'] ?? 'trial'), ['trial', 'contact'], true)
                ? $data['request_type']
                : 'trial';

            $trialRequest = TrialRequest::query()->create([
                'gym_id' => $gym->id,
                'branch_id' => $branch?->id,
                'member_id' => $actor?->hasRole(RoleName::Member->value) ? $actor->id : null,
                'request_type' => $requestType,
                'source' => 'public_gym_profile',
                'name' => $data['name'] ?? $actor?->name,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? $actor?->email,
                'preferred_date' => $data['preferred_date'] ?? Carbon::today()->toDateString(),
                'preferred_time' => $data['preferred_time'] ?? null,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            if ($gym->owner) {
                $this->notificationService->create(
                    user: $gym->owner,
                    type: NotificationType::TrialBooking->value,
                    title: 'New Trial Request',
                    body: 'A new trial request has been created for your gym.',
                    gymId: $gym->id,
                    branchId: $branch?->id,
                    data: ['trial_request_id' => $trialRequest->id],
                );
            }

            if ($actor) {
                $this->notificationService->create(
                    user: $actor,
                    type: NotificationType::TrialBooking->value,
                    title: 'Trial Request Received',
                    body: 'Your trial request has been recorded.',
                    gymId: $gym->id,
                    branchId: $branch?->id,
                    data: ['trial_request_id' => $trialRequest->id],
                );
            }

            $this->auditLogService->log(
                event: 'trial_request.created',
                action: 'create',
                request: $request,
                subject: $trialRequest,
                gym: $gym,
                branch: $branch,
                newValues: $trialRequest->toArray(),
            );

            return $trialRequest->load(['gym', 'branch', 'member', 'assignedTrainer']);
        });
    }

    public function createForMember(User $member, array $data, ?Request $request = null): TrialRequest
    {
        return $this->createPublic(array_merge($data, [
            'name' => $data['name'] ?? $member->name,
            'email' => $data['email'] ?? $member->email,
        ]), $member, $request);
    }

    public function queryForActor(User $actor, ?Request $request = null): Builder
    {
        $query = TrialRequest::query()
            ->with(['gym', 'branch', 'member', 'assignedTrainer'])
            ->latest('id');

        if ($actor->active_role === RoleName::PlatformAdmin->value) {
            return $query;
        }

        if ($actor->active_role === RoleName::GymOwner->value) {
            $query->whereHas('gym', fn ($builder) => $builder->where('owner_user_id', $actor->id));
        } elseif ($actor->active_role === RoleName::BranchManager->value) {
            $branchIds = $this->scopeResolver->branchesQuery($actor)->pluck('branches.id');
            $query->whereIn('branch_id', $branchIds);
        } elseif ($actor->active_role === RoleName::GymStaff->value) {
            $branchIds = $this->scopeResolver->branchesQuery($actor)->pluck('branches.id');
            $query->whereIn('branch_id', $branchIds);
        } elseif ($actor->active_role === RoleName::Trainer->value) {
            $query->where('assigned_trainer_id', $actor->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request?->filled('gym_id')) {
            $query->where('gym_id', $request->integer('gym_id'));
        }

        if ($request?->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request?->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request?->filled('request_type')) {
            $query->where('request_type', $request->string('request_type'));
        }

        if ($request?->filled('assigned_trainer_id')) {
            $query->where('assigned_trainer_id', $request->integer('assigned_trainer_id'));
        }

        if ($request?->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search)
                ->orWhere('phone', 'like', $search));
        }

        if ($request?->filled('start_date')) {
            $query->whereDate('preferred_date', '>=', $request->date('start_date'));
        }

        if ($request?->filled('end_date')) {
            $query->whereDate('preferred_date', '<=', $request->date('end_date'));
        }

        return $query;
    }

    public function resolveForActor(User $actor, TrialRequest $trialRequest): TrialRequest
    {
        return $this->queryForActor($actor)
            ->whereKey($trialRequest->id)
            ->firstOrFail();
    }

    public function updateForActor(User $actor, TrialRequest $trialRequest, array $data, ?Request $request = null): TrialRequest
    {
        $trialRequest = $this->resolveForActor($actor, $trialRequest);

        if ($actor->active_role === RoleName::Trainer->value && array_key_exists('assigned_trainer_id', $data)) {
            throw ValidationException::withMessages([
                'assigned_trainer_id' => ['Trainers cannot reassign trial requests.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $trialRequest, $data, $request): TrialRequest {
            $oldValues = $trialRequest->toArray();

            if (array_key_exists('assigned_trainer_id', $data) && $data['assigned_trainer_id']) {
                $trainerProfile = TrainerProfile::query()
                    ->where('user_id', $data['assigned_trainer_id'])
                    ->where('gym_id', $trialRequest->gym_id)
                    ->when($trialRequest->branch_id, fn ($query) => $query->where(function ($builder) use ($trialRequest): void {
                        $builder->whereNull('branch_id')->orWhere('branch_id', $trialRequest->branch_id);
                    }))
                    ->first();

                if (! $trainerProfile) {
                    throw ValidationException::withMessages([
                        'assigned_trainer_id' => ['The assigned trainer must belong to the same gym or branch.'],
                    ]);
                }
            }

            if ($actor->active_role === RoleName::Trainer->value) {
                unset($data['assigned_trainer_id']);
            }

            $trialRequest->fill($data);
            $trialRequest->save();

            if ($trialRequest->member && array_key_exists('status', $data)) {
                $this->notificationService->create(
                    user: $trialRequest->member,
                    type: NotificationType::TrialBooking->value,
                    title: 'Trial Request Updated',
                    body: 'Your trial request status has been updated.',
                    gymId: $trialRequest->gym_id,
                    branchId: $trialRequest->branch_id,
                    data: [
                        'trial_request_id' => $trialRequest->id,
                        'status' => $trialRequest->status,
                    ],
                );
            }

            if (! empty($data['assigned_trainer_id']) && $trialRequest->assignedTrainer) {
                $this->notificationService->create(
                    user: $trialRequest->assignedTrainer,
                    type: NotificationType::TrialBooking->value,
                    title: 'Trial Request Assigned',
                    body: 'A trial request has been assigned to you.',
                    gymId: $trialRequest->gym_id,
                    branchId: $trialRequest->branch_id,
                    data: ['trial_request_id' => $trialRequest->id],
                );
            }

            $this->auditLogService->log(
                event: 'trial_request.updated',
                action: 'update',
                request: $request,
                subject: $trialRequest,
                gym: $trialRequest->gym,
                branch: $trialRequest->branch,
                oldValues: $oldValues,
                newValues: $trialRequest->fresh()->toArray(),
            );

            return $trialRequest->fresh(['gym', 'branch', 'member', 'assignedTrainer']);
        });
    }

    public function assignTrainer(User $actor, TrialRequest $trialRequest, ?int $trainerId, ?string $notes = null, ?Request $request = null): TrialRequest
    {
        $payload = ['assigned_trainer_id' => $trainerId];

        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        return $this->updateForActor($actor, $trialRequest, $payload, $request);
    }

    public function accept(User $actor, TrialRequest $trialRequest, ?string $notes = null, ?Request $request = null): TrialRequest
    {
        return $this->transition($actor, $trialRequest, 'accepted', $notes, $request, 'trial_request.accepted');
    }

    public function reject(User $actor, TrialRequest $trialRequest, ?string $notes = null, ?Request $request = null): TrialRequest
    {
        return $this->transition($actor, $trialRequest, 'rejected', $notes, $request, 'trial_request.rejected');
    }

    public function complete(User $actor, TrialRequest $trialRequest, ?string $notes = null, ?Request $request = null): TrialRequest
    {
        return $this->transition($actor, $trialRequest, 'completed', $notes, $request, 'trial_request.completed');
    }

    public function convert(User $actor, TrialRequest $trialRequest, array $data = [], ?Request $request = null): array
    {
        $trialRequest = $this->resolveForActor($actor, $trialRequest);

        return DB::transaction(function () use ($actor, $trialRequest, $data, $request): array {
            $existingMember = $this->resolveExistingMemberUser($trialRequest, $data);
            $payload = [
                'name' => $data['name'] ?? $trialRequest->name ?? $existingMember?->name ?? 'Trial Member',
                'email' => $data['email'] ?? $trialRequest->email ?? $existingMember?->email ?? ('trial+'.Str::random(10).'@example.com'),
                'password' => $data['password'] ?? 'TrialMember@123',
                'branch_id' => $data['branch_id'] ?? $trialRequest->branch_id,
                'assigned_trainer_user_id' => $data['assigned_trainer_user_id'] ?? $trialRequest->assigned_trainer_id,
                'fitness_goal' => $data['fitness_goal'] ?? null,
                'medical_notes' => $data['medical_notes'] ?? null,
                'injury_notes' => $data['injury_notes'] ?? null,
                'membership_status' => 'inactive',
                'is_active' => true,
            ];

            if (Schema::hasColumn('users', 'phone')) {
                $payload['phone'] = $data['phone'] ?? $trialRequest->phone ?? $existingMember?->phone;
            }

            $memberUser = $this->managedUserService->upsertMember($existingMember, $trialRequest->gym, $payload);

            $oldValues = $trialRequest->toArray();
            $trialRequest->forceFill([
                'member_id' => $memberUser->id,
                'assigned_trainer_id' => $data['assigned_trainer_user_id'] ?? $trialRequest->assigned_trainer_id,
                'status' => 'converted',
                'notes' => $this->mergedNotes($trialRequest->notes, $data['notes'] ?? null),
            ])->save();

            $this->auditLogService->log(
                event: 'trial_request.converted',
                action: 'update',
                request: $request,
                subject: $trialRequest,
                gym: $trialRequest->gym,
                branch: $trialRequest->branch,
                oldValues: $oldValues,
                newValues: array_merge($trialRequest->fresh()->toArray(), ['converted_member_id' => $memberUser->id]),
            );

            if ($trialRequest->member) {
                $this->notificationService->create(
                    user: $trialRequest->member,
                    type: NotificationType::TrialBooking->value,
                    title: 'Trial Request Converted',
                    body: 'Your trial request has been converted into a gym member record.',
                    gymId: $trialRequest->gym_id,
                    branchId: $trialRequest->branch_id,
                    data: ['trial_request_id' => $trialRequest->id, 'member_user_id' => $memberUser->id],
                );
            }

            return [
                'trial_request' => $trialRequest->fresh(['gym', 'branch', 'member', 'assignedTrainer']),
                'member' => $memberUser->fresh(['memberProfile']),
            ];
        });
    }

    private function transition(User $actor, TrialRequest $trialRequest, string $status, ?string $notes, ?Request $request, string $event): TrialRequest
    {
        $payload = ['status' => $status];

        if ($notes !== null) {
            $payload['notes'] = $this->mergedNotes($trialRequest->notes, $notes);
        }

        $updated = $this->updateForActor($actor, $trialRequest, $payload, $request);

        $this->auditLogService->log(
            event: $event,
            action: 'update',
            request: $request,
            subject: $updated,
            gym: $updated->gym,
            branch: $updated->branch,
            newValues: ['status' => $updated->status, 'notes' => $updated->notes],
        );

        return $updated;
    }

    private function resolveExistingMemberUser(TrialRequest $trialRequest, array $data): ?User
    {
        if (! empty($data['existing_user_id'])) {
            return User::query()->find($data['existing_user_id']);
        }

        if ($trialRequest->member_id) {
            return User::query()->find($trialRequest->member_id);
        }

        if ($trialRequest->email) {
            $byEmail = User::query()->firstWhere('email', $trialRequest->email);

            if ($byEmail) {
                return $byEmail;
            }
        }

        if ($trialRequest->phone && Schema::hasColumn('users', 'phone')) {
            return User::query()->firstWhere('phone', $trialRequest->phone);
        }

        return null;
    }

    private function mergedNotes(?string $currentNotes, ?string $newNotes): ?string
    {
        $currentNotes = trim((string) $currentNotes);
        $newNotes = trim((string) $newNotes);

        if ($newNotes === '') {
            return $currentNotes !== '' ? $currentNotes : null;
        }

        if ($currentNotes === '') {
            return $newNotes;
        }

        return $currentNotes."\n".$newNotes;
    }
}
