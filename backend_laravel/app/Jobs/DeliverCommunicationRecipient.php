<?php

namespace App\Jobs;

use App\Enums\CommunicationChannel;
use App\Models\Branch;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationRecipient;
use App\Models\WhatsAppBusinessAccount;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\Notification\NotificationService;
use App\Services\WhatsApp\MetaWhatsAppClient;
use App\Services\WhatsApp\WhatsAppConsentService;
use App\Services\WhatsApp\WhatsAppTemplateParameterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliverCommunicationRecipient implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $recipientId)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(NotificationService $notifications, MetaWhatsAppClient $meta): void
    {
        $recipient = DB::transaction(function (): ?CommunicationRecipient {
            $recipient = CommunicationRecipient::query()->lockForUpdate()->find($this->recipientId);
            $staleProcessing = $recipient?->status === 'processing'
                && $recipient->updated_at?->isBefore(now()->subMinutes(10));
            if (! $recipient
                || (! in_array($recipient->status, ['pending', 'failed'], true) && ! $staleProcessing)
                || $recipient->attempt_count >= $this->tries) {
                return null;
            }
            $recipient->forceFill([
                'status' => 'processing',
                'attempt_count' => $recipient->attempt_count + 1,
                'last_error' => null,
            ])->save();

            return $recipient->load(['campaign', 'channelDefinition.whatsappTemplate', 'user']);
        });
        if (! $recipient || ! $recipient->user || ! $recipient->campaign) {
            return;
        }

        try {
            if ($recipient->channel === CommunicationChannel::InApp->value) {
                $this->deliverInApp($recipient, $notifications);
            } else {
                $this->deliverWhatsApp($recipient, $meta);
            }
        } catch (Throwable $exception) {
            $recipient->forceFill([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();
            $this->finishCampaignIfReady($recipient->campaign);
            throw $exception;
        }

        $this->finishCampaignIfReady($recipient->campaign);
    }

    private function deliverInApp(CommunicationRecipient $recipient, NotificationService $notifications): void
    {
        $channel = $recipient->channelDefinition;
        $notification = $notifications->create(
            user: $recipient->user,
            type: $channel->notification_type ?: 'manual_campaign',
            title: (string) $channel->title,
            body: (string) $channel->body,
            gymId: $recipient->campaign->gym_id,
            branchId: $recipient->campaign->branch_id,
            createdByUserId: $recipient->campaign->created_by_user_id,
            data: ['campaign_id' => $recipient->campaign->id, 'app_role' => 'member'],
        );
        $recipient->forceFill([
            'status' => $notification ? 'sent' : 'skipped',
            'exclusion_reason' => $notification ? null : 'notification_preference_disabled',
            'sent_at' => $notification ? now() : null,
        ])->save();
    }

    private function deliverWhatsApp(CommunicationRecipient $recipient, MetaWhatsAppClient $meta): void
    {
        $template = $recipient->channelDefinition->whatsappTemplate;
        if (! $template || $template->status !== 'approved') {
            $recipient->forceFill(['status' => 'skipped', 'exclusion_reason' => 'template_not_approved'])->save();

            return;
        }
        $purpose = strtolower((string) $template->category) === 'marketing' ? 'marketing' : 'utility';
        $eligibility = app(WhatsAppConsentService::class)->deliveryEligibility($recipient->user, $recipient->campaign->gym_id, $purpose);
        $destination = $eligibility['phone'];
        if (! $destination) {
            $recipient->forceFill(['status' => 'skipped', 'exclusion_reason' => $eligibility['exclusion_reason']])->save();

            return;
        }
        $recipient->forceFill(['destination' => $destination])->save();

        $account = WhatsAppBusinessAccount::query()
            ->where('gym_id', $recipient->campaign->gym_id)
            ->where('id', $template->whatsapp_business_account_id)
            ->where('status', 'connected')
            ->where('health_status', 'healthy')
            ->where(fn (Builder $query) => $query
                ->whereNull('token_expires_at')
                ->orWhere('token_expires_at', '>', now()))
            ->firstOrFail();
        $phone = $account->phoneNumbers()->where('is_primary', true)->where('is_active', true)->firstOrFail();
        $messageId = $meta->sendTemplate(
            $phone->phone_number_id,
            (string) $account->access_token,
            (string) $recipient->destination,
            $template->name,
            $template->language,
            app(WhatsAppTemplateParameterService::class)->componentsFromReplacements(
                $recipient->channelDefinition->template_parameters ?? [],
                [
                    '{member_name}' => $recipient->user->name ?: 'Member',
                    '{notification_title}' => $recipient->channelDefinition->title ?: $recipient->campaign->name,
                    '{notification_message}' => $recipient->channelDefinition->body ?: $recipient->campaign->name,
                    '{gym_name}' => $recipient->campaign->gym?->name ?: 'Atlas',
                    '{branch_name}' => $recipient->campaign->branch_id
                        ? (string) (Branch::query()->whereKey($recipient->campaign->branch_id)->value('name') ?: 'your branch')
                        : 'your branch',
                ],
            ),
        );
        $conversation = WhatsAppConversation::query()->firstOrCreate([
            'whatsapp_phone_number_id' => $phone->id,
            'contact_wa_id' => ltrim((string) $recipient->destination, '+'),
        ], [
            'whatsapp_business_account_id' => $account->id,
            'user_id' => $recipient->user_id,
            'contact_name' => $recipient->recipient_snapshot['name'] ?? null,
            'status' => 'open',
        ]);
        WhatsAppMessage::query()->firstOrCreate([
            'provider_message_id' => $messageId,
        ], [
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'message_type' => 'template',
            'payload' => [
                'template_name' => $template->name,
                'language' => $template->language,
                'components' => $recipient->channelDefinition->template_parameters ?? [],
            ],
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();
        $recipient->forceFill([
            'status' => 'sent',
            'provider_message_id' => $messageId,
            'sent_at' => now(),
        ])->save();
    }

    private function finishCampaignIfReady(CommunicationCampaign $campaign): void
    {
        $unfinished = $campaign->recipients()
            ->where(fn ($query) => $query
                ->whereIn('status', ['pending', 'processing'])
                ->orWhere(fn ($failed) => $failed->where('status', 'failed')->where('attempt_count', '<', $this->tries)))
            ->exists();
        if (! $unfinished) {
            $campaign->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        }
    }
}
