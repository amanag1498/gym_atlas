<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Models\AnnouncementRecipient;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function create(
        User $user,
        string $type,
        string $title,
        string $body,
        ?int $gymId = null,
        ?int $branchId = null,
        ?int $createdByUserId = null,
        ?int $announcementId = null,
        ?int $membershipId = null,
        ?array $data = null,
        mixed $scheduledFor = null,
    ): ?Notification {
        if (! $this->isEnabled($user->id, $type, $gymId, $branchId)) {
            return null;
        }

        $gymName = $data['gym_name'] ?? ($gymId ? Gym::query()->whereKey($gymId)->value('name') : null);
        $branchName = $data['branch_name'] ?? ($branchId ? Branch::query()->whereKey($branchId)->value('name') : null);
        $context = array_filter([
            'source' => $gymId ? 'gym' : 'platform',
            'gym_name' => $gymName,
            'branch_name' => $branchName,
        ], fn (mixed $value): bool => $value !== null);

        return Notification::query()->create([
            'user_id' => $user->id,
            'gym_id' => $gymId,
            'branch_id' => $branchId,
            'announcement_id' => $announcementId,
            'member_membership_id' => $membershipId,
            'type' => $type,
            'title' => $title,
            'message' => $body,
            'body' => $body,
            'data' => [...$context, ...($data ?? [])],
            'created_by_user_id' => $createdByUserId,
            'scheduled_for' => $scheduledFor,
        ]);
    }

    public function listForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate($perPage);
    }

    public function markRead(Notification $notification): Notification
    {
        DB::transaction(function () use ($notification): void {
            $readAt = now();
            $notification->forceFill(['read_at' => $readAt])->save();
            AnnouncementRecipient::query()
                ->where('notification_id', $notification->id)
                ->update(['read_at' => $readAt]);
        });

        return $notification;
    }

    public function markUnread(Notification $notification): Notification
    {
        DB::transaction(function () use ($notification): void {
            $notification->forceFill(['read_at' => null])->save();
            AnnouncementRecipient::query()
                ->where('notification_id', $notification->id)
                ->update(['read_at' => null]);
        });

        return $notification;
    }

    public function markAllRead(User $user, ?int $gymId = null, ?int $branchId = null): int
    {
        $query = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->when($gymId !== null, fn ($query) => $query->where('gym_id', $gymId))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId));
        $notificationIds = (clone $query)->pluck('id');

        return DB::transaction(function () use ($query, $notificationIds): int {
            $readAt = now();
            $updated = $query->update(['read_at' => $readAt]);
            AnnouncementRecipient::query()
                ->whereIn('notification_id', $notificationIds)
                ->update(['read_at' => $readAt]);

            return $updated;
        });
    }

    public function isEnabled(int $userId, string $type, ?int $gymId = null, ?int $branchId = null): bool
    {
        if ($this->isCriticalType($type)) {
            return true;
        }

        $query = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('notification_type', $type);

        if ($gymId !== null || $branchId !== null) {
            $scopedPreference = (clone $query)
                ->where('gym_id', $gymId)
                ->where('branch_id', $branchId)
                ->first();

            if ($scopedPreference !== null) {
                return $scopedPreference->is_enabled;
            }
        }

        $preference = (clone $query)
            ->whereNull('gym_id')
            ->whereNull('branch_id')
            ->first();

        return $preference?->is_enabled ?? true;
    }

    private function isCriticalType(string $type): bool
    {
        return in_array($type, [
            NotificationType::MembershipExpiry->value,
            NotificationType::MembershipPaused->value,
            NotificationType::MembershipResumed->value,
            NotificationType::PaymentDue->value,
            NotificationType::CustomDue->value,
            'trainer_gym_invitation',
            'independent_trainer_invitation',
            'independent_coaching_response',
            'independent_coaching_revoked',
            'independent_trainer_verification',
        ], true);
    }
}
