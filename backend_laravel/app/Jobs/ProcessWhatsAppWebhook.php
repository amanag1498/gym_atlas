<?php

namespace App\Jobs;

use App\Models\CommunicationRecipient;
use App\Models\NotificationDelivery;
use App\Models\WhatsAppConsent;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('webhooks');
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [5, 30, 120, 600];
    }

    public function handle(): void
    {
        $event = DB::transaction(function (): ?WhatsAppWebhookEvent {
            $event = WhatsAppWebhookEvent::query()->lockForUpdate()->find($this->eventId);
            if (! $event
                || $event->status === 'processed'
                || ($event->status === 'processing' && $event->updated_at?->isAfter(now()->subMinutes(10)))
                || $event->attempt_count >= $this->tries) {
                return null;
            }
            $event->forceFill(['status' => 'processing', 'attempt_count' => $event->attempt_count + 1])->save();

            return $event;
        });
        if (! $event) {
            return;
        }

        try {
            foreach (($event->payload['entry'] ?? []) as $entry) {
                foreach (($entry['changes'] ?? []) as $change) {
                    $this->processValue($change['value'] ?? []);
                }
            }
            $event->forceFill(['status' => 'processed', 'processed_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $exception) {
            $event->forceFill(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 4000)])->save();
            throw $exception;
        }
    }

    private function processValue(array $value): void
    {
        foreach (($value['statuses'] ?? []) as $status) {
            $this->processStatus($status);
        }
        foreach (($value['messages'] ?? []) as $message) {
            $this->processInbound($value, $message);
        }
    }

    private function processStatus(array $status): void
    {
        $messageId = (string) ($status['id'] ?? '');
        $state = strtolower((string) ($status['status'] ?? ''));
        if ($messageId === '' || ! in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $updates = ['status' => $state];
        if ($state === 'sent') {
            $updates['sent_at'] = now();
        }
        if ($state === 'delivered') {
            $updates['delivered_at'] = now();
        }
        if ($state === 'read') {
            $updates['read_at'] = now();
        }
        if ($state === 'failed') {
            $updates['last_error'] = data_get($status, 'errors.0.title', 'WhatsApp delivery failed.');
        }
        CommunicationRecipient::query()->where('provider_message_id', $messageId)->get()
            ->each(fn (CommunicationRecipient $recipient) => $this->applyMonotonicStatus($recipient, $state, $updates));
        WhatsAppMessage::query()->where('provider_message_id', $messageId)->get()
            ->each(fn (WhatsAppMessage $message) => $this->applyMonotonicStatus($message, $state, $updates));
        $deliveryUpdates = ['status' => $state];
        foreach (['sent_at', 'delivered_at', 'read_at'] as $timestamp) {
            if (array_key_exists($timestamp, $updates)) {
                $deliveryUpdates[$timestamp] = $updates[$timestamp];
            }
        }
        if ($state === 'failed') {
            $deliveryUpdates += [
                'failed_at' => now(),
                'error_code' => 'whatsapp_delivery_failed',
                'error_message' => $updates['last_error'] ?? null,
            ];
        }
        NotificationDelivery::query()->where('provider_message_id', $messageId)->get()
            ->each(fn (NotificationDelivery $delivery) => $this->applyMonotonicStatus($delivery, $state, $deliveryUpdates));
    }

    private function processInbound(array $value, array $message): void
    {
        $providerMessageId = (string) ($message['id'] ?? '');
        $phoneNumberId = (string) data_get($value, 'metadata.phone_number_id', '');
        $contactWaId = (string) ($message['from'] ?? '');
        if ($providerMessageId === '' || $phoneNumberId === '' || $contactWaId === '') {
            return;
        }

        $phone = WhatsAppPhoneNumber::query()->with('account')->where('phone_number_id', $phoneNumberId)->first();
        if (! $phone) {
            return;
        }
        $phoneE164 = '+'.preg_replace('/\D+/', '', $contactWaId);
        $consent = WhatsAppConsent::query()
            ->where('phone_e164', $phoneE164)
            ->where('gym_id', $phone->account?->gym_id)
            ->latest('id')
            ->first();
        $conversation = WhatsAppConversation::query()->firstOrCreate([
            'whatsapp_phone_number_id' => $phone->id,
            'contact_wa_id' => $contactWaId,
        ], [
            'whatsapp_business_account_id' => $phone->whatsapp_business_account_id,
            'user_id' => $consent?->user_id,
            'contact_name' => data_get($value, 'contacts.0.profile.name'),
            'status' => 'open',
        ]);
        $body = data_get($message, 'text.body');
        WhatsAppMessage::query()->firstOrCreate([
            'provider_message_id' => $providerMessageId,
        ], [
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_type' => $message['type'] ?? null,
            'body' => $body,
            'payload' => $message,
            'status' => 'received',
        ]);
        $conversation->forceFill([
            'user_id' => $conversation->user_id ?: $consent?->user_id,
            'contact_name' => $conversation->contact_name ?: data_get($value, 'contacts.0.profile.name'),
            'service_window_expires_at' => now()->addHours(24),
            'last_message_at' => now(),
        ])->save();

        if (in_array(strtoupper(trim((string) $body)), ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'], true)) {
            WhatsAppConsent::query()
                ->where('phone_e164', $phoneE164)
                ->where('gym_id', $phone->account?->gym_id)
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'source' => 'inbound_keyword',
                    'evidence' => [
                        'provider_message_id' => $providerMessageId,
                        'keyword' => strtoupper(trim((string) $body)),
                        'sender_phone_number_id' => $phoneNumberId,
                    ],
                ]);
        }
    }

    private function applyMonotonicStatus(Model $model, string $state, array $updates): void
    {
        $rank = ['queued' => 0, 'processing' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
        if (($rank[$state] ?? -1) < ($rank[(string) $model->getAttribute('status')] ?? -1)) {
            return;
        }
        $model->forceFill($updates)->save();
    }
}
