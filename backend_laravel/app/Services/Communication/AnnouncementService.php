<?php

namespace App\Services\Communication;

use App\Enums\AnnouncementAudienceType;
use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\Notification;
use App\Models\User;
use App\Services\Authorization\ScopeResolver;
use App\Services\Members\GymMemberAccessService;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly GymMemberAccessService $gymMemberAccessService,
        private readonly NotificationService $notificationService,
    ) {}

    public function createAnnouncement(User $actor, array $data): Announcement
    {
        return DB::transaction(function () use ($actor, $data): Announcement {
            $gym = isset($data['gym_id']) ? Gym::query()->findOrFail($data['gym_id']) : null;
            $branch = isset($data['branch_id']) ? Branch::query()->findOrFail($data['branch_id']) : null;

            $this->assertAnnouncementScope($actor, $data['audience_type'], $gym, $branch, $data['member_ids'] ?? []);

            $sendAt = isset($data['send_at']) ? now()->parse($data['send_at']) : now();
            $isScheduled = $sendAt->isFuture();
            $announcement = Announcement::query()->create([
                'gym_id' => $gym?->id,
                'branch_id' => $branch?->id,
                'created_by_user_id' => $actor->id,
                'created_by' => $actor->id,
                'audience_type' => $data['audience_type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'status' => $isScheduled ? 'scheduled' : 'processing',
                'is_platform_wide' => $data['audience_type'] === AnnouncementAudienceType::PlatformWide->value,
                'send_at' => $sendAt,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $recipients = $this->resolveRecipients($actor, $data['audience_type'], $gym, $branch, $data['member_ids'] ?? []);

            foreach ($recipients as $recipient) {
                AnnouncementRecipient::query()->create([
                    'announcement_id' => $announcement->id,
                    'user_id' => $recipient->id,
                    'gym_id' => $gym?->id,
                    'branch_id' => $branch?->id,
                    'notification_id' => null,
                ]);
            }

            if (! $isScheduled) {
                $this->deliverAnnouncement($announcement);
            }

            return $announcement->loadCount('recipients');
        });
    }

    public function dispatchDueAnnouncements(int $limit = 100): int
    {
        $ids = Announcement::query()
            ->where('status', 'scheduled')
            ->where('send_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $announcement = Announcement::query()->lockForUpdate()->find($id);
                if (! $announcement || $announcement->status !== 'scheduled' || $announcement->send_at?->isFuture()) {
                    return;
                }
                $announcement->forceFill(['status' => 'processing'])->save();
                $this->deliverAnnouncement($announcement);
            });
        }

        return $ids->count();
    }

    private function deliverAnnouncement(Announcement $announcement): void
    {
        $announcement->loadMissing(['gym', 'branch', 'recipients.user']);
        foreach ($announcement->recipients as $recipientRecord) {
            if ($recipientRecord->notification_id || ! $recipientRecord->user) {
                continue;
            }
            $notification = $this->notificationService->create(
                user: $recipientRecord->user,
                type: $announcement->audience_type === AnnouncementAudienceType::TrainerAssignment->value
                    ? NotificationType::TrainerAssignment->value
                    : NotificationType::GymAnnouncement->value,
                title: $announcement->title,
                body: $announcement->message,
                gymId: $announcement->gym_id,
                branchId: $announcement->branch_id,
                createdByUserId: $announcement->created_by_user_id,
                announcementId: $announcement->id,
                data: [
                    'audience_type' => $announcement->audience_type,
                    'source' => $announcement->gym_id ? 'gym' : 'platform',
                    'gym_name' => $announcement->gym?->name,
                    'branch_name' => $announcement->branch?->name,
                    'app_role' => $announcement->audience_type === AnnouncementAudienceType::TrainerAssignment->value
                        ? 'trainer'
                        : 'member',
                ],
                scheduledFor: $announcement->send_at,
            );
            $recipientRecord->forceFill(['notification_id' => $notification?->id])->save();
        }
        $announcement->forceFill(['status' => 'sent'])->save();
    }

    public function listAnnouncementsForActor(
        User $actor,
        ?int $gymId = null,
        ?int $branchId = null,
        array $filters = [],
        string $pageName = 'page',
    ) {
        return $this->announcementQueryForActor($actor, $gymId, $branchId, $filters)
            ->with(['creator:id,name,email', 'gym:id,name', 'branch:id,name'])
            ->withCount([
                'recipients',
                'recipients as read_recipients_count' => fn ($query) => $query->whereNotNull('read_at'),
            ])
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 15), ['*'], $pageName);
    }

    /**
     * @return array{total:int,gym_wide:int,branch_specific:int,selected_members:int,recipients:int,read_recipients:int}
     */
    public function summaryForActor(
        User $actor,
        ?int $gymId = null,
        ?int $branchId = null,
        array $filters = [],
    ): array {
        $query = $this->announcementQueryForActor($actor, $gymId, $branchId, $filters);
        $counts = (clone $query)
            ->selectRaw('audience_type, COUNT(*) as aggregate')
            ->groupBy('audience_type')
            ->pluck('aggregate', 'audience_type');
        $recipientQuery = AnnouncementRecipient::query()
            ->whereIn('announcement_id', (clone $query)->select('announcements.id'));

        return [
            'total' => (int) $counts->sum(),
            'gym_wide' => (int) ($counts[AnnouncementAudienceType::GymWide->value] ?? 0),
            'branch_specific' => (int) ($counts[AnnouncementAudienceType::BranchSpecific->value] ?? 0),
            'selected_members' => (int) ($counts[AnnouncementAudienceType::SelectedMembers->value] ?? 0),
            'recipients' => (clone $recipientQuery)->count(),
            'read_recipients' => (clone $recipientQuery)->whereNotNull('read_at')->count(),
        ];
    }

    private function announcementQueryForActor(
        User $actor,
        ?int $gymId,
        ?int $branchId,
        array $filters,
    ): Builder {
        return Announcement::query()
            ->when($actor->active_role !== RoleName::PlatformAdmin->value, function ($query) use ($actor): void {
                $gymIds = $this->scopeResolver->gymsQuery($actor)->pluck('gyms.id');
                $branchIds = $this->scopeResolver->branchesQuery($actor)->pluck('branches.id');

                $query->where(function ($builder) use ($actor, $gymIds, $branchIds): void {
                    $builder->where(function ($inner) use ($actor, $gymIds, $branchIds): void {
                        $inner->whereIn('gym_id', $gymIds);

                        if (in_array($actor->active_role, [RoleName::BranchManager->value, RoleName::GymStaff->value], true)) {
                            $inner->where(function ($branchQuery) use ($branchIds): void {
                                $branchQuery->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                            });
                        }
                    })->orWhere('is_platform_wide', true);
                });
            })
            ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
            ->when($branchId, fn ($query) => $query->where(function ($scope) use ($branchId): void {
                $scope->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->when(($filters['search'] ?? null) !== null && $filters['search'] !== '', function ($query) use ($filters): void {
                $search = '%'.trim((string) $filters['search']).'%';

                $query->where(function ($builder) use ($search): void {
                    $builder->where('title', 'like', $search)
                        ->orWhere('message', 'like', $search);
                });
            })
            ->when(($filters['audience_type'] ?? null) !== null && $filters['audience_type'] !== '', fn ($query) => $query->where('audience_type', $filters['audience_type']));
    }

    public function resolveAnnouncementForActor(User $actor, Announcement $announcement): Announcement
    {
        $query = Announcement::query()
            ->when($actor->active_role !== RoleName::PlatformAdmin->value, function ($builder) use ($actor): void {
                $gymIds = $this->scopeResolver->gymsQuery($actor)->pluck('gyms.id');
                $branchIds = $this->scopeResolver->branchesQuery($actor)->pluck('branches.id');

                $builder->where(function ($query) use ($gymIds, $branchIds): void {
                    $query->where(function ($inner) use ($gymIds, $branchIds): void {
                        $inner->whereIn('gym_id', $gymIds);

                        if ($branchIds->isNotEmpty()) {
                            $inner->where(function ($branchQuery) use ($branchIds): void {
                                $branchQuery->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                            });
                        }
                    })->orWhere('is_platform_wide', true);
                });
            });

        return $query->whereKey($announcement->id)->firstOrFail();
    }

    public function showAnnouncementForActor(User $actor, Announcement $announcement): Announcement
    {
        return $this->resolveAnnouncementForActor($actor, $announcement)
            ->load([
                'creator:id,name,email',
                'gym:id,name',
                'branch:id,name',
                'recipients.user:id,name,email',
            ])
            ->loadCount([
                'recipients',
                'recipients as read_recipients_count' => fn ($query) => $query->whereNotNull('read_at'),
            ]);
    }

    public function deleteAnnouncement(User $actor, Announcement $announcement): void
    {
        $announcement = $this->resolveAnnouncementForActor($actor, $announcement);

        DB::transaction(function () use ($announcement): void {
            Notification::query()->where('announcement_id', $announcement->id)->delete();
            AnnouncementRecipient::query()->where('announcement_id', $announcement->id)->delete();
            $announcement->delete();
        });
    }

    private function assertAnnouncementScope(User $actor, string $audienceType, ?Gym $gym, ?Branch $branch, array $memberIds): void
    {
        if ($audienceType === AnnouncementAudienceType::PlatformWide->value) {
            if ($actor->active_role !== RoleName::PlatformAdmin->value) {
                throw ValidationException::withMessages([
                    'audience_type' => ['Only platform admins can send platform-wide announcements.'],
                ]);
            }

            return;
        }

        if (! $gym || ! $this->scopeResolver->canAccessGym($actor, $gym)) {
            throw ValidationException::withMessages([
                'gym_id' => ['You do not have access to this gym announcement scope.'],
            ]);
        }

        if ($branch && ! $this->scopeResolver->canAccessBranch($actor, $branch)) {
            throw ValidationException::withMessages([
                'branch_id' => ['You do not have access to this branch announcement scope.'],
            ]);
        }

        if ($branch && (int) $branch->gym_id !== (int) $gym->id) {
            throw ValidationException::withMessages([
                'branch_id' => ['The selected branch does not belong to the announcement gym.'],
            ]);
        }

        if ($memberIds !== []) {
            $accessibleMemberIds = $this->accessibleMemberProfiles($gym, $branch)
                ->whereIn('user_id', $memberIds)
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if (array_diff(array_map('intval', $memberIds), $accessibleMemberIds) !== []) {
                throw ValidationException::withMessages([
                    'member_ids' => ['Selected members must have current access to this gym and branch.'],
                ]);
            }
        }

        if ($actor->active_role === RoleName::BranchManager->value && ! $branch) {
            throw ValidationException::withMessages([
                'branch_id' => ['Branch managers can notify only their own branch.'],
            ]);
        }

        if ($actor->active_role === RoleName::BranchManager->value
            && $audienceType === AnnouncementAudienceType::GymWide->value) {
            throw ValidationException::withMessages([
                'audience_type' => ['Branch managers cannot send gym-wide announcements.'],
            ]);
        }

        if ($actor->active_role === RoleName::Trainer->value) {
            $assignedMemberIds = $this->accessibleMemberProfiles($gym, $branch)
                ->where('assigned_trainer_user_id', $actor->id)
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($memberIds === [] || array_diff($memberIds, $assignedMemberIds) !== []) {
                throw ValidationException::withMessages([
                    'member_ids' => ['Trainers can notify only assigned members.'],
                ]);
            }
        }
    }

    private function resolveRecipients(User $actor, string $audienceType, ?Gym $gym, ?Branch $branch, array $memberIds): Collection
    {
        return match ($audienceType) {
            AnnouncementAudienceType::PlatformWide->value => User::query()->get(),
            AnnouncementAudienceType::GymWide->value,
            AnnouncementAudienceType::Offer->value => User::query()
                ->whereHas('memberProfiles', function (Builder $query) use ($gym): void {
                    $query->where('gym_id', $gym?->id);
                    $this->gymMemberAccessService->scopeAccessibleProfiles($query);
                })
                ->get(),
            AnnouncementAudienceType::BranchSpecific->value => User::query()
                ->whereHas('memberProfiles', function (Builder $query) use ($gym, $branch): void {
                    $query->where('gym_id', $gym?->id)->where('branch_id', $branch?->id);
                    $this->gymMemberAccessService->scopeAccessibleProfiles($query);
                })
                ->get(),
            AnnouncementAudienceType::SelectedMembers->value,
            AnnouncementAudienceType::TrainerAssignment->value => User::query()
                ->whereIn('id', $memberIds)
                ->whereHas('memberProfiles', function (Builder $query) use ($gym, $branch): void {
                    $query->where('gym_id', $gym?->id)
                        ->when($branch, fn (Builder $builder) => $builder->where('branch_id', $branch->id));
                    $this->gymMemberAccessService->scopeAccessibleProfiles($query);
                })
                ->get(),
            default => collect(),
        };
    }

    private function accessibleMemberProfiles(Gym $gym, ?Branch $branch): Builder
    {
        $query = MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->when($branch, fn (Builder $builder) => $builder->where('branch_id', $branch->id));

        return $this->gymMemberAccessService->scopeAccessibleProfiles($query);
    }
}
