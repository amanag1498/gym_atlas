<?php

namespace App\Services\Notification;

use App\Enums\CommunicationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationTransport;
use App\Enums\NotificationType;
use App\Jobs\DeliverNotificationOutbox;
use App\Models\AnnouncementRecipient;
use App\Models\Branch;
use App\Models\CommunicationAutomationRule;
use App\Models\CommunicationOutbox;
use App\Models\Gym;
use App\Models\Notification;
use App\Models\NotificationChannelPreference;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\CommunicationScope;
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
        $memberInAppPreferenceEnabled = $this->isChannelEnabled(
            $user->id,
            $type,
            CommunicationChannel::InApp,
            $gymId,
            $branchId,
        );
        $isMemberDelivery = ($data['app_role'] ?? $user->active_role) === 'member';
        $automationRule = $isMemberDelivery
            ? $this->automationRule($type, $gymId, $branchId)
            : null;
        $inAppEnabled = $memberInAppPreferenceEnabled
            && ($automationRule?->in_app_enabled ?? true);
        $whatsAppEnabled = $isMemberDelivery
            && ($automationRule?->whatsapp_enabled ?? false)
            && $this->isChannelEnabled(
                $user->id,
                $type,
                CommunicationChannel::WhatsApp,
                $gymId,
                $branchId,
            );
        if (! $inAppEnabled && ! $whatsAppEnabled) {
            return null;
        }

        $gymName = $data['gym_name'] ?? ($gymId ? Gym::query()->whereKey($gymId)->value('name') : null);
        $branchName = $data['branch_name'] ?? ($branchId ? Branch::query()->whereKey($branchId)->value('name') : null);
        $context = array_filter([
            'source' => $gymId ? 'gym' : 'platform',
            'gym_name' => $gymName,
            'branch_name' => $branchName,
        ], fn (mixed $value): bool => $value !== null);

        return DB::transaction(function () use (
            $user,
            $type,
            $title,
            $body,
            $gymId,
            $branchId,
            $createdByUserId,
            $announcementId,
            $membershipId,
            $data,
            $scheduledFor,
            $context,
            $inAppEnabled,
            $automationRule,
        ): Notification {
            $notification = Notification::query()->create([
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
                'in_app_visible' => $inAppEnabled,
            ]);

            NotificationDelivery::query()->create([
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'gym_id' => $notification->gym_id,
                'branch_id' => $notification->branch_id,
                'channel' => CommunicationChannel::InApp->value,
                'transport' => NotificationTransport::Database->value,
                'status' => $inAppEnabled
                    ? NotificationDeliveryStatus::Delivered->value
                    : NotificationDeliveryStatus::Skipped->value,
                'attempt_count' => 1,
                'target_count' => $inAppEnabled ? 1 : 0,
                'success_count' => $inAppEnabled ? 1 : 0,
                'queued_at' => now(),
                'sent_at' => $inAppEnabled ? now() : null,
                'delivered_at' => $inAppEnabled ? now() : null,
                'error_code' => $inAppEnabled
                    ? null
                    : ($automationRule && ! $automationRule->in_app_enabled
                        ? 'automation_channel_disabled'
                        : 'channel_preference_disabled'),
            ]);

            $outbox = CommunicationOutbox::query()->create([
                'event_type' => 'notification.created',
                'aggregate_type' => Notification::class,
                'aggregate_id' => $notification->id,
                'idempotency_key' => 'notification:'.$notification->id.':deliver',
                'payload' => ['notification_id' => $notification->id],
                'status' => 'pending',
                'available_at' => now(),
            ]);

            DB::afterCommit(fn () => DeliverNotificationOutbox::dispatch($outbox->id));

            return $notification;
        });
    }

    public function listForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('in_app_visible', true)
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
            NotificationDelivery::query()
                ->where('notification_id', $notification->id)
                ->where('transport', NotificationTransport::Database->value)
                ->update([
                    'status' => NotificationDeliveryStatus::Read->value,
                    'read_at' => $readAt,
                ]);
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
            NotificationDelivery::query()
                ->where('notification_id', $notification->id)
                ->where('transport', NotificationTransport::Database->value)
                ->update([
                    'status' => NotificationDeliveryStatus::Delivered->value,
                    'read_at' => null,
                ]);
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
            NotificationDelivery::query()
                ->whereIn('notification_id', $notificationIds)
                ->where('transport', NotificationTransport::Database->value)
                ->update([
                    'status' => NotificationDeliveryStatus::Read->value,
                    'read_at' => $readAt,
                ]);

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

    public function isChannelEnabled(
        int $userId,
        string $type,
        CommunicationChannel $channel,
        ?int $gymId = null,
        ?int $branchId = null,
    ): bool {
        if ($channel === CommunicationChannel::InApp && $this->isCriticalType($type)) {
            return true;
        }

        $scoped = NotificationChannelPreference::query()
            ->where('user_id', $userId)
            ->where('notification_type', $type)
            ->where('channel', $channel->value)
            ->where('scope_key', CommunicationScope::key($gymId, $branchId))
            ->first();
        if ($scoped) {
            return $scoped->is_enabled;
        }

        if ($gymId !== null || $branchId !== null) {
            $global = NotificationChannelPreference::query()
                ->where('user_id', $userId)
                ->where('notification_type', $type)
                ->where('channel', $channel->value)
                ->where('scope_key', CommunicationScope::key(null))
                ->first();
            if ($global) {
                return $global->is_enabled;
            }
        }

        return $channel === CommunicationChannel::InApp
            ? $this->isEnabled($userId, $type, $gymId, $branchId)
            : true;
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
            NotificationType::EventBookingConfirmed->value,
            NotificationType::EventWaitlistPromoted->value,
            NotificationType::EventCancelled->value,
            NotificationType::EventBookingCancelled->value,
        ], true);
    }

    private function automationRule(string $type, ?int $gymId, ?int $branchId): ?CommunicationAutomationRule
    {
        $rules = CommunicationAutomationRule::query()
            ->where('gym_id', $gymId)
            ->where('notification_type', $type)
            ->where('recipient_role', 'member')
            ->where('is_enabled', true)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->get();

        return $rules->firstWhere('branch_id', $branchId) ?? $rules->firstWhere('branch_id', null);
    }
}
