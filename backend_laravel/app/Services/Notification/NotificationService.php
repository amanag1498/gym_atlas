<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
            'data' => $data,
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
        $notification->forceFill(['read_at' => now()])->save();

        return $notification;
    }

    public function markUnread(Notification $notification): Notification
    {
        $notification->forceFill(['read_at' => null])->save();

        return $notification;
    }

    public function markAllRead(User $user, ?int $gymId = null, ?int $branchId = null): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->when($gymId !== null, fn ($query) => $query->where('gym_id', $gymId))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->update(['read_at' => now()]);
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
            \App\Enums\NotificationType::MembershipExpiry->value,
            \App\Enums\NotificationType::PaymentDue->value,
            \App\Enums\NotificationType::CustomDue->value,
        ], true);
    }
}
