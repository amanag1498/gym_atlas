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
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function createAnnouncement(User $actor, array $data): Announcement
    {
        return DB::transaction(function () use ($actor, $data): Announcement {
            $gym = isset($data['gym_id']) ? Gym::query()->findOrFail($data['gym_id']) : null;
            $branch = isset($data['branch_id']) ? Branch::query()->findOrFail($data['branch_id']) : null;

            $this->assertAnnouncementScope($actor, $data['audience_type'], $gym, $branch, $data['member_ids'] ?? []);

            $announcement = Announcement::query()->create([
                'gym_id' => $gym?->id,
                'branch_id' => $branch?->id,
                'created_by_user_id' => $actor->id,
                'created_by' => $actor->id,
                'audience_type' => $data['audience_type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'status' => 'sent',
                'is_platform_wide' => $data['audience_type'] === AnnouncementAudienceType::PlatformWide->value,
                'send_at' => $data['send_at'] ?? now(),
                'metadata' => $data['metadata'] ?? null,
            ]);

            $recipients = $this->resolveRecipients($actor, $data['audience_type'], $gym, $branch, $data['member_ids'] ?? []);

            foreach ($recipients as $recipient) {
                $notification = $this->notificationService->create(
                    user: $recipient,
                    type: $data['audience_type'] === AnnouncementAudienceType::TrainerAssignment->value
                        ? NotificationType::TrainerAssignment->value
                        : NotificationType::GymAnnouncement->value,
                    title: $data['title'],
                    body: $data['message'],
                    gymId: $gym?->id,
                    branchId: $branch?->id,
                    createdByUserId: $actor->id,
                    announcementId: $announcement->id,
                    data: [
                        'audience_type' => $data['audience_type'],
                        'member_ids' => $data['member_ids'] ?? [],
                    ],
                    scheduledFor: $data['send_at'] ?? now(),
                );

                AnnouncementRecipient::query()->create([
                    'announcement_id' => $announcement->id,
                    'user_id' => $recipient->id,
                    'gym_id' => $gym?->id,
                    'branch_id' => $branch?->id,
                    'notification_id' => $notification?->id,
                ]);
            }

            return $announcement->loadCount('recipients');
        });
    }

    public function listAnnouncementsForActor(
        User $actor,
        ?int $gymId = null,
        ?int $branchId = null,
        array $filters = [],
    )
    {
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
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(($filters['search'] ?? null) !== null && $filters['search'] !== '', function ($query) use ($filters): void {
                $search = '%'.trim((string) $filters['search']).'%';

                $query->where(function ($builder) use ($search): void {
                    $builder->where('title', 'like', $search)
                        ->orWhere('message', 'like', $search);
                });
            })
            ->when(($filters['audience_type'] ?? null) !== null && $filters['audience_type'] !== '', fn ($query) => $query->where('audience_type', $filters['audience_type']))
            ->with(['creator:id,name,email', 'gym:id,name', 'branch:id,name'])
            ->withCount([
                'recipients',
                'recipients as read_recipients_count' => fn ($query) => $query->whereNotNull('read_at'),
            ])
            ->latest('id')
            ->paginate(15);
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

        if ($actor->active_role === RoleName::BranchManager->value && ! $branch) {
            throw ValidationException::withMessages([
                'branch_id' => ['Branch managers can notify only their own branch.'],
            ]);
        }

        if ($actor->active_role === RoleName::Trainer->value) {
            $assignedMemberIds = MemberProfile::query()
                ->where('assigned_trainer_user_id', $actor->id)
                ->where('gym_id', $gym->id)
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
                ->whereHas('memberProfile', fn ($query) => $query->where('gym_id', $gym?->id))
                ->get(),
            AnnouncementAudienceType::BranchSpecific->value => User::query()
                ->whereHas('memberProfile', fn ($query) => $query->where('branch_id', $branch?->id))
                ->get(),
            AnnouncementAudienceType::SelectedMembers->value,
            AnnouncementAudienceType::TrainerAssignment->value => User::query()
                ->whereIn('id', $memberIds)
                ->get(),
            default => collect(),
        };
    }
}
