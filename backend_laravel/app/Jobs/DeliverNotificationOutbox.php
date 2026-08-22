<?php

namespace App\Jobs;

use App\Enums\CommunicationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationTransport;
use App\Enums\RoleName;
use App\Models\CommunicationAutomationRule;
use App\Models\CommunicationOutbox;
use App\Models\Notification;
use App\Models\NotificationChannelPreference;
use App\Models\NotificationDelivery;
use App\Models\UserFcmToken;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\Firebase\FcmNotificationService;
use App\Services\WhatsApp\MetaWhatsAppClient;
use App\Services\WhatsApp\WhatsAppConsentService;
use App\Services\WhatsApp\WhatsAppTemplateParameterService;
use App\Support\CommunicationScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliverNotificationOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public readonly int $outboxId)
    {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [5, 30, 120, 300];
    }

    public function handle(FcmNotificationService $fcm, ?MetaWhatsAppClient $meta = null): void
    {
        $outbox = $this->claim();
        if (! $outbox) {
            return;
        }

        try {
            $notification = Notification::query()
                ->with('user.roles')
                ->find($outbox->aggregate_id);

            if (! $notification || ! $notification->user) {
                $this->complete($outbox, 'Notification or recipient no longer exists.');

                return;
            }

            if ($notification->in_app_visible) {
                $this->publishRealtime($notification);
                $this->sendFirebase($notification, $fcm);
            }
            $this->sendWhatsApp($notification, $meta ?? app(MetaWhatsAppClient::class));
            $this->complete($outbox);
        } catch (Throwable $exception) {
            $outbox->forceFill([
                'status' => 'failed',
                'available_at' => now()->addSeconds($this->retryDelay()),
                'locked_at' => null,
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }

    private function claim(): ?CommunicationOutbox
    {
        return DB::transaction(function (): ?CommunicationOutbox {
            $outbox = CommunicationOutbox::query()->lockForUpdate()->find($this->outboxId);

            if (! $outbox
                || $outbox->status === 'processed'
                || ($outbox->status === 'processing' && $outbox->locked_at?->isAfter(now()->subMinutes(10)))
                || $outbox->attempt_count >= $this->tries
                || ($outbox->available_at && $outbox->available_at->isFuture())) {
                return null;
            }

            $outbox->forceFill([
                'status' => 'processing',
                'attempt_count' => $outbox->attempt_count + 1,
                'locked_at' => now(),
                'last_error' => null,
            ])->save();

            return $outbox;
        });
    }

    private function publishRealtime(Notification $notification): void
    {
        $delivery = NotificationDelivery::query()->firstOrCreate([
            'notification_id' => $notification->id,
            'transport' => NotificationTransport::Realtime->value,
        ], $this->deliveryDefaults($notification, NotificationTransport::Realtime, NotificationDeliveryStatus::Queued));

        if (in_array($delivery->status, [
            NotificationDeliveryStatus::Sent->value,
            NotificationDeliveryStatus::Delivered->value,
            NotificationDeliveryStatus::Read->value,
        ], true)) {
            return;
        }

        PublishRealtimeEvent::dispatch('internal/notifications', [
            'userId' => $notification->user_id,
            'title' => $notification->title,
            'body' => $notification->body,
            'type' => $notification->type,
            'gymId' => $notification->gym_id,
            'branchId' => $notification->branch_id,
            'data' => [
                ...($notification->data ?? []),
                'notification_id' => $notification->id,
                'type' => $notification->type,
            ],
        ]);

        $delivery->forceFill([
            'status' => NotificationDeliveryStatus::Sent->value,
            'attempt_count' => $delivery->attempt_count + 1,
            'success_count' => 1,
            'sent_at' => now(),
            'error_code' => null,
            'error_message' => null,
        ])->save();
    }

    private function sendFirebase(Notification $notification, FcmNotificationService $fcm): void
    {
        $appRole = $this->appRole($notification);
        $targetCount = UserFcmToken::query()
            ->deliverable()
            ->where('user_id', $notification->user_id)
            ->where('app_role', $appRole)
            ->count();
        $delivery = NotificationDelivery::query()->firstOrCreate([
            'notification_id' => $notification->id,
            'transport' => NotificationTransport::Firebase->value,
        ], [
            ...$this->deliveryDefaults($notification, NotificationTransport::Firebase, NotificationDeliveryStatus::Queued),
            'target_count' => $targetCount,
            'metadata' => ['app_role' => $appRole],
        ]);

        if ($delivery->status === NotificationDeliveryStatus::Sent->value) {
            return;
        }

        if ($targetCount === 0) {
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::Skipped->value,
                'attempt_count' => $delivery->attempt_count + 1,
                'error_code' => 'no_registered_device',
                'error_message' => 'No active Firebase token is registered for the target app.',
            ])->save();

            return;
        }

        if (! $fcm->isConfigured()) {
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::Failed->value,
                'attempt_count' => $delivery->attempt_count + 1,
                'target_count' => $targetCount,
                'failed_at' => now(),
                'error_code' => 'firebase_not_configured',
                'error_message' => 'Firebase Admin credentials are unavailable.',
            ])->save();

            return;
        }

        $sent = $fcm->sendToUser(
            user: $notification->user,
            title: $notification->title,
            body: $notification->body,
            data: [
                ...($notification->data ?? []),
                'notification_id' => $notification->id,
                'type' => $notification->type,
            ],
            appRole: $appRole,
        );

        $delivery->forceFill([
            'status' => $sent > 0
                ? NotificationDeliveryStatus::Sent->value
                : NotificationDeliveryStatus::Failed->value,
            'attempt_count' => $delivery->attempt_count + 1,
            'target_count' => $targetCount,
            'success_count' => $sent,
            'sent_at' => $sent > 0 ? now() : null,
            'failed_at' => $sent > 0 ? null : now(),
            'error_code' => $sent > 0 ? null : 'firebase_send_failed',
            'error_message' => $sent > 0
                ? ($sent < $targetCount ? 'Some registered devices rejected the notification.' : null)
                : 'Firebase rejected all target-device sends.',
        ])->save();
    }

    private function appRole(Notification $notification): string
    {
        $explicit = $notification->data['app_role'] ?? null;
        if (in_array($explicit, ['member', 'trainer', 'admin'], true)) {
            return $explicit;
        }

        return match ($notification->user->active_role) {
            RoleName::Member->value => 'member',
            RoleName::Trainer->value => 'trainer',
            default => 'admin',
        };
    }

    private function sendWhatsApp(Notification $notification, MetaWhatsAppClient $meta): void
    {
        if ($this->appRole($notification) !== 'member') {
            return;
        }

        $preference = NotificationChannelPreference::query()
            ->where('user_id', $notification->user_id)
            ->where('notification_type', $notification->type)
            ->where('channel', CommunicationChannel::WhatsApp->value)
            ->whereIn('scope_key', array_unique([
                CommunicationScope::key($notification->gym_id, $notification->branch_id),
                CommunicationScope::key($notification->gym_id),
                CommunicationScope::key(null),
            ]))
            ->orderByRaw('CASE scope_key WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [
                CommunicationScope::key($notification->gym_id, $notification->branch_id),
                CommunicationScope::key($notification->gym_id),
            ])
            ->first();
        if ($preference && ! $preference->is_enabled) {
            return;
        }

        $rules = CommunicationAutomationRule::query()
            ->where('gym_id', $notification->gym_id)
            ->where('notification_type', $notification->type)
            ->where('recipient_role', 'member')
            ->where('is_enabled', true)
            ->where('whatsapp_enabled', true)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $notification->branch_id))
            ->with('whatsappTemplate.account.phoneNumbers')
            ->get();
        $rule = $rules->firstWhere('branch_id', $notification->branch_id) ?? $rules->firstWhere('branch_id', null);
        $template = $rule?->whatsappTemplate;
        $account = $template?->account;
        $phone = $account?->phoneNumbers->first(fn ($item) => $item->is_primary && $item->is_active);
        $accountAvailable = $account
            && $account->status === 'connected'
            && $account->health_status === 'healthy'
            && (! $account->token_expires_at || $account->token_expires_at->isFuture());
        if (! $rule || ! $template || $template->status !== 'approved' || ! $accountAvailable || ! $phone) {
            return;
        }

        $purpose = strtolower((string) $template->category) === 'marketing' ? 'marketing' : 'utility';
        $eligibility = app(WhatsAppConsentService::class)->deliveryEligibility($notification->user, $notification->gym_id, $purpose);
        $destination = $eligibility['phone'];
        $delivery = NotificationDelivery::query()->firstOrCreate([
            'notification_id' => $notification->id,
            'transport' => NotificationTransport::WhatsApp->value,
        ], [
            ...$this->deliveryDefaults($notification, NotificationTransport::WhatsApp, NotificationDeliveryStatus::Queued),
            'channel' => CommunicationChannel::WhatsApp->value,
            'target_count' => $destination ? 1 : 0,
            'metadata' => ['automation_rule_id' => $rule->id, 'template_id' => $template->id],
        ]);
        if (in_array($delivery->status, ['sent', 'delivered', 'read', 'skipped'], true)) {
            return;
        }
        if (! $destination) {
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::Skipped->value,
                'attempt_count' => $delivery->attempt_count + 1,
                'error_code' => $eligibility['exclusion_reason'],
                'error_message' => $eligibility['exclusion_reason'] === 'whatsapp_opted_out'
                    ? 'The member opted out of WhatsApp messages for this gym.'
                    : 'The member does not have a valid mobile number.',
            ])->save();

            return;
        }

        try {
            $messageId = $meta->sendTemplate(
                $phone->phone_number_id,
                (string) $account->access_token,
                $destination,
                $template->name,
                $template->language,
                app(WhatsAppTemplateParameterService::class)->components(
                    $notification,
                    $rule->configuration['template_parameter_values'] ?? [],
                ),
            );
            $conversation = WhatsAppConversation::query()->firstOrCreate([
                'whatsapp_phone_number_id' => $phone->id,
                'contact_wa_id' => ltrim($destination, '+'),
            ], [
                'whatsapp_business_account_id' => $account->id,
                'user_id' => $notification->user_id,
                'contact_name' => $notification->user->name,
            ]);
            WhatsAppMessage::query()->firstOrCreate(['provider_message_id' => $messageId], [
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'message_type' => 'template',
                'payload' => ['template_id' => $template->id],
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::Sent->value,
                'attempt_count' => $delivery->attempt_count + 1,
                'target_count' => 1,
                'success_count' => 1,
                'provider_message_id' => $messageId,
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ])->save();
            $rule->forceFill(['last_triggered_at' => now()])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::Failed->value,
                'attempt_count' => $delivery->attempt_count + 1,
                'failed_at' => now(),
                'error_code' => 'whatsapp_send_failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();
            throw $exception;
        }
    }

    private function deliveryDefaults(
        Notification $notification,
        NotificationTransport $transport,
        NotificationDeliveryStatus $status,
    ): array {
        return [
            'user_id' => $notification->user_id,
            'gym_id' => $notification->gym_id,
            'branch_id' => $notification->branch_id,
            'channel' => CommunicationChannel::InApp->value,
            'transport' => $transport->value,
            'status' => $status->value,
            'queued_at' => now(),
        ];
    }

    private function complete(CommunicationOutbox $outbox, ?string $note = null): void
    {
        $outbox->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
            'locked_at' => null,
            'last_error' => $note,
        ])->save();
    }

    private function retryDelay(): int
    {
        $attempt = max(1, $this->attempts());

        return [1 => 5, 2 => 30, 3 => 120, 4 => 300][$attempt] ?? 300;
    }
}
