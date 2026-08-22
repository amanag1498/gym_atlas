<?php

namespace App\Services\Communication;

use App\Enums\CommunicationChannel;
use App\Jobs\StartCommunicationCampaign;
use App\Models\CommunicationCampaign;
use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppBusinessAccount;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppConsentService;
use App\Services\WhatsApp\WhatsAppTemplateParameterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunicationCampaignService
{
    public function __construct(
        private readonly WhatsAppTemplateParameterService $templateParameters,
        private readonly WhatsAppConsentService $whatsappPreferences,
    ) {}

    public function create(?Gym $gym, User $actor, array $data): CommunicationCampaign
    {
        return DB::transaction(function () use ($gym, $actor, $data): CommunicationCampaign {
            $campaign = CommunicationCampaign::query()->create([
                'gym_id' => $gym?->id,
                'branch_id' => $data['branch_id'] ?? null,
                'name' => $data['name'],
                'audience_type' => $data['audience_type'],
                'audience_filters' => ['member_ids' => $data['member_ids'] ?? []],
                'status' => 'draft',
                'scheduled_for' => $data['scheduled_for'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($data['channels'] as $channel => $content) {
                if (! in_array($channel, [CommunicationChannel::InApp->value, CommunicationChannel::WhatsApp->value], true)) {
                    continue;
                }
                if ($channel === CommunicationChannel::WhatsApp->value) {
                    $template = WhatsAppTemplate::query()
                        ->whereKey($content['whatsapp_template_id'] ?? 0)
                        ->whereHas('account', fn (Builder $account) => $account->where('gym_id', $gym?->id))
                        ->first();
                    if (! $template) {
                        throw ValidationException::withMessages([
                            'channels.whatsapp.whatsapp_template_id' => ['Select a template from the connected sender for this scope.'],
                        ]);
                    }
                    $content['template_parameters'] = $this->templateParameters->validate(
                        $template,
                        $content['template_parameters'] ?? [],
                    );
                }
                $campaign->channels()->create([
                    'channel' => $channel,
                    'notification_type' => $content['notification_type'] ?? 'manual_campaign',
                    'title' => $content['title'] ?? null,
                    'body' => $content['body'] ?? null,
                    'whatsapp_template_id' => $content['whatsapp_template_id'] ?? null,
                    'template_parameters' => $content['template_parameters'] ?? [],
                ]);
            }

            if ($campaign->channels()->doesntExist()) {
                throw ValidationException::withMessages(['channels' => ['Select at least one supported channel.']]);
            }

            return $campaign->load(['channels.whatsappTemplate']);
        });
    }

    public function preview(CommunicationCampaign $campaign): array
    {
        $channels = $campaign->loadMissing('channels.whatsappTemplate')->channels;
        $audienceCount = $this->audienceQuery($campaign)->count('users.id');
        $summary = ['audience' => $audienceCount, 'eligible' => 0, 'excluded' => 0, 'by_channel' => []];

        foreach ($channels as $channel) {
            $eligible = 0;
            $reasons = [];
            $this->audienceQuery($campaign)
                ->select(['users.id', 'users.name', 'users.email', 'users.phone'])
                ->chunkById(500, function ($users) use ($campaign, $channel, &$eligible, &$reasons): void {
                    $eligibility = $this->eligibilityForUsers($campaign, $channel, $users->pluck('id')->all());
                    foreach ($users as $user) {
                        $reason = $eligibility['general_reason']
                            ?? ($eligibility['exclusion_reasons'][$user->id] ?? null);
                        if ($reason) {
                            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                        } else {
                            $eligible++;
                        }
                    }
                }, 'users.id', 'id');
            $summary['eligible'] += $eligible;
            $summary['excluded'] += $audienceCount - $eligible;
            $summary['by_channel'][$channel->channel] = [
                'audience' => $audienceCount,
                'eligible' => $eligible,
                'excluded' => $audienceCount - $eligible,
                'exclusion_reasons' => $reasons,
            ];
        }

        return $summary;
    }

    public function schedule(CommunicationCampaign $campaign, ?string $scheduledFor = null): CommunicationCampaign
    {
        abort_unless(in_array($campaign->status, ['draft', 'scheduled'], true), 422, 'Only a draft campaign can be scheduled.');
        $targetTime = $scheduledFor ? Carbon::parse($scheduledFor) : ($campaign->scheduled_for ?? now());
        $this->assertMarketingSendingWindow($campaign, $targetTime);
        $campaign->forceFill([
            'status' => 'scheduled',
            'scheduled_for' => $targetTime,
        ])->save();

        if ($campaign->scheduled_for->isPast()) {
            StartCommunicationCampaign::dispatch($campaign->id);
        }

        return $campaign->fresh(['channels.whatsappTemplate']);
    }

    public function snapshotRecipients(CommunicationCampaign $campaign): int
    {
        return DB::transaction(function () use ($campaign): int {
            if ($campaign->recipients()->exists()) {
                return $campaign->recipients()->count();
            }

            $campaign->loadMissing('channels.whatsappTemplate');
            $this->audienceQuery($campaign)
                ->select(['users.id', 'users.name', 'users.email', 'users.phone'])
                ->chunkById(500, function ($users) use ($campaign): void {
                    foreach ($campaign->channels as $channel) {
                        $eligibility = $this->eligibilityForUsers($campaign, $channel, $users->pluck('id')->all());
                        foreach ($users as $user) {
                            $reason = $eligibility['general_reason']
                                ?? ($eligibility['exclusion_reasons'][$user->id] ?? null);
                            $destination = $channel->channel === CommunicationChannel::WhatsApp->value
                                ? ($eligibility['destinations'][$user->id] ?? null)
                                : (string) $user->id;
                            $campaign->recipients()->create([
                                'communication_campaign_channel_id' => $channel->id,
                                'user_id' => $user->id,
                                'channel' => $channel->channel,
                                'destination' => $destination,
                                'status' => $reason ? 'skipped' : 'pending',
                                'exclusion_reason' => $reason,
                                'recipient_snapshot' => [
                                    'name' => $user->name,
                                    'email' => $user->email,
                                    'phone' => $destination,
                                ],
                            ]);
                        }
                    }
                }, 'users.id', 'id');

            return $campaign->recipients()->count();
        });
    }

    private function audienceQuery(CommunicationCampaign $campaign): Builder
    {
        $query = User::query()->where('is_active', true);
        if ($campaign->gym_id) {
            $query->whereHas('memberProfiles', fn (Builder $profile) => $profile
                ->where('gym_id', $campaign->gym_id)
                ->where('is_active', true)
                ->when($campaign->branch_id, fn (Builder $branch) => $branch->where('branch_id', $campaign->branch_id)));
        } else {
            $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'member'));
        }

        $memberIds = $campaign->audience_filters['member_ids'] ?? [];
        if ($campaign->audience_type === 'selected_members') {
            $query->whereKey($memberIds);
        }

        return $query->distinct();
    }

    /** @return array{general_reason:?string,destinations?:array<int,string>,exclusion_reasons?:array<int,string>} */
    private function eligibilityForUsers(CommunicationCampaign $campaign, $channel, array $userIds): array
    {
        if ($channel->channel === CommunicationChannel::InApp->value) {
            return ['general_reason' => null];
        }
        if ($channel->channel !== CommunicationChannel::WhatsApp->value) {
            return ['general_reason' => 'unsupported_channel'];
        }
        $template = $channel->whatsappTemplate;
        if (! $template || $template->status !== 'approved') {
            return ['general_reason' => 'template_not_approved'];
        }
        $account = WhatsAppBusinessAccount::query()
            ->where('gym_id', $campaign->gym_id)
            ->where('id', $template->whatsapp_business_account_id)
            ->where('status', 'connected')
            ->where('health_status', 'healthy')
            ->where(fn (Builder $query) => $query
                ->whereNull('token_expires_at')
                ->orWhere('token_expires_at', '>', now()))
            ->whereHas('phoneNumbers', fn (Builder $query) => $query->where('is_primary', true)->where('is_active', true))
            ->exists();
        if (! $account) {
            return ['general_reason' => 'sender_unavailable'];
        }

        $destinations = [];
        $exclusionReasons = [];
        $users = User::query()->whereKey($userIds)->get();
        $eligibilities = $this->whatsappPreferences->deliveryEligibilities($users, $campaign->gym_id, $this->consentPurpose($template));
        $users->each(function (User $user) use ($eligibilities, &$destinations, &$exclusionReasons): void {
            $eligibility = $eligibilities[$user->id];
            if ($eligibility['phone']) {
                $destinations[$user->id] = $eligibility['phone'];
            } else {
                $exclusionReasons[$user->id] = $eligibility['exclusion_reason'];
            }
        });

        return ['general_reason' => null, 'destinations' => $destinations, 'exclusion_reasons' => $exclusionReasons];
    }

    private function consentPurpose(?WhatsAppTemplate $template): string
    {
        return strtolower((string) $template?->category) === 'marketing' ? 'marketing' : 'utility';
    }

    private function assertMarketingSendingWindow(CommunicationCampaign $campaign, Carbon $targetTime): void
    {
        $hasMarketing = $campaign->channels()
            ->whereHas('whatsappTemplate', fn (Builder $query) => $query->whereRaw('LOWER(category) = ?', ['marketing']))
            ->exists();
        if (! $hasMarketing) {
            return;
        }
        $timezone = $campaign->gym?->timezone
            ?: (string) config('services.meta_whatsapp.platform_timezone', 'Asia/Kolkata');
        $local = $targetTime->copy()->timezone($timezone);
        $minute = ($local->hour * 60) + $local->minute;
        [$startHour, $startMinute] = array_map('intval', explode(':', (string) config('services.meta_whatsapp.quiet_hours_start', '21:00')));
        [$endHour, $endMinute] = array_map('intval', explode(':', (string) config('services.meta_whatsapp.quiet_hours_end', '08:00')));
        $start = ($startHour * 60) + $startMinute;
        $end = ($endHour * 60) + $endMinute;
        $isQuiet = $start <= $end
            ? $minute >= $start && $minute < $end
            : $minute >= $start || $minute < $end;
        if ($isQuiet) {
            throw ValidationException::withMessages([
                'scheduled_for' => ["Marketing WhatsApp campaigns cannot send between {$startHour}:".str_pad((string) $startMinute, 2, '0', STR_PAD_LEFT)." and {$endHour}:".str_pad((string) $endMinute, 2, '0', STR_PAD_LEFT)." ({$timezone})."],
            ]);
        }
    }
}
