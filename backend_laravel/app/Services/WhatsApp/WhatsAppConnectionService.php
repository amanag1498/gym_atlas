<?php

namespace App\Services\WhatsApp;

use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppBusinessAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppConnectionService
{
    public function __construct(private readonly MetaWhatsAppClient $meta) {}

    public function configuration(): array
    {
        return $this->meta->embeddedSignupConfiguration();
    }

    public function accountFor(?Gym $gym): ?WhatsAppBusinessAccount
    {
        return WhatsAppBusinessAccount::query()
            ->where('gym_id', $gym?->id)
            ->with(['phoneNumbers', 'templates' => fn ($query) => $query->orderBy('name')->orderBy('language')])
            ->latest('id')
            ->first();
    }

    public function connect(
        ?Gym $gym,
        User $actor,
        string $code,
        string $wabaId,
        ?string $phoneNumberId,
    ): WhatsAppBusinessAccount {
        if (! $this->meta->isConfigured()) {
            throw ValidationException::withMessages([
                'configuration' => ['Meta WhatsApp Embedded Signup is not configured for this environment.'],
            ]);
        }

        $existingWaba = WhatsAppBusinessAccount::query()->where('waba_id', $wabaId)->first();
        if ($existingWaba && (int) $existingWaba->gym_id !== (int) $gym?->id) {
            throw ValidationException::withMessages([
                'waba_id' => ['This WhatsApp Business account is already connected to another Atlas scope.'],
            ]);
        }

        $exchange = $this->meta->exchangeEmbeddedSignupCode($code);
        $accessToken = $exchange['access_token'];
        $business = $this->meta->businessAccount($wabaId, $accessToken);
        if ((string) ($business['id'] ?? '') !== $wabaId) {
            throw ValidationException::withMessages([
                'waba_id' => ['The signup session does not match the selected WhatsApp Business account.'],
            ]);
        }

        $phones = collect($this->meta->phoneNumbers($wabaId, $accessToken));
        if ($phones->isEmpty()) {
            throw ValidationException::withMessages([
                'phone_number_id' => ['Meta did not return a phone number for this WhatsApp Business account.'],
            ]);
        }
        if ($phoneNumberId && ! $phones->contains(fn (array $phone): bool => (string) ($phone['id'] ?? '') === $phoneNumberId)) {
            throw ValidationException::withMessages([
                'phone_number_id' => ['The selected number does not belong to this WhatsApp Business account.'],
            ]);
        }
        if (! $phoneNumberId) {
            $coexistencePhone = $phones->first(fn (array $phone): bool => filter_var($phone['is_on_biz_app'] ?? false, FILTER_VALIDATE_BOOL));
            $phoneNumberId = (string) (($coexistencePhone ?? $phones->first())['id'] ?? '');
        }

        $this->meta->subscribeApp($wabaId, $accessToken);

        $account = DB::transaction(function () use (
            $gym,
            $actor,
            $wabaId,
            $phoneNumberId,
            $accessToken,
            $exchange,
            $business,
            $phones,
        ): WhatsAppBusinessAccount {
            WhatsAppBusinessAccount::query()
                ->where('gym_id', $gym?->id)
                ->where('waba_id', '!=', $wabaId)
                ->update([
                    'status' => 'disconnected',
                    'access_token' => null,
                    'disconnected_at' => now(),
                ]);

            $account = WhatsAppBusinessAccount::query()->updateOrCreate([
                'waba_id' => $wabaId,
            ], [
                'gym_id' => $gym?->id,
                'business_name' => $business['name'] ?? null,
                'access_token' => $accessToken,
                'token_expires_at' => $exchange['expires_in']
                    ? now()->addSeconds($exchange['expires_in'])
                    : null,
                'status' => 'connected',
                'health_status' => 'healthy',
                'last_error' => null,
                'connected_at' => now(),
                'last_synced_at' => now(),
                'disconnected_at' => null,
                'connected_by_user_id' => $actor->id,
            ]);

            foreach ($phones as $phone) {
                WhatsAppPhoneNumber::query()->updateOrCreate([
                    'phone_number_id' => (string) $phone['id'],
                ], [
                    'whatsapp_business_account_id' => $account->id,
                    'display_phone_number' => $phone['display_phone_number'] ?? null,
                    'verified_name' => $phone['verified_name'] ?? null,
                    'quality_rating' => $phone['quality_rating'] ?? null,
                    'code_verification_status' => $phone['code_verification_status'] ?? null,
                    'is_primary' => (string) $phone['id'] === $phoneNumberId,
                    'is_active' => true,
                    'metadata' => $phone,
                ]);
            }

            return $account;
        });

        $this->syncTemplates($account);

        return $account->fresh(['phoneNumbers', 'templates']);
    }

    public function syncTemplates(WhatsAppBusinessAccount $account): int
    {
        try {
            $templates = $this->meta->templates($account->waba_id, (string) $account->access_token);
        } catch (Throwable $exception) {
            $account->forceFill([
                'health_status' => 'degraded',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }

        DB::transaction(function () use ($account, $templates): void {
            foreach ($templates as $template) {
                WhatsAppTemplate::query()->updateOrCreate([
                    'whatsapp_business_account_id' => $account->id,
                    'name' => (string) $template['name'],
                    'language' => (string) $template['language'],
                ], [
                    'provider_template_id' => $template['id'] ?? null,
                    'category' => isset($template['category']) ? strtolower((string) $template['category']) : null,
                    'status' => strtolower((string) ($template['status'] ?? 'pending')),
                    'quality_rating' => data_get($template, 'quality_score.score'),
                    'components' => $template['components'] ?? [],
                    'metadata' => $template,
                    'last_synced_at' => now(),
                ]);
            }

            $account->forceFill([
                'last_synced_at' => now(),
                'health_status' => 'healthy',
                'last_error' => null,
            ])->save();
        });

        return count($templates);
    }

    public function disconnect(WhatsAppBusinessAccount $account): void
    {
        $accessToken = $account->access_token ? (string) $account->access_token : null;
        DB::transaction(function () use ($account): void {
            $account->forceFill([
                'status' => 'disconnected',
                'health_status' => 'disconnected',
                'access_token' => null,
                'disconnected_at' => now(),
            ])->save();
            $account->phoneNumbers()->update(['is_active' => false]);
        });

        if ($accessToken) {
            try {
                $this->meta->unsubscribeApp($account->waba_id, $accessToken);
            } catch (Throwable $exception) {
                $account->forceFill([
                    'last_error' => 'Disconnected locally; Meta unsubscribe failed: '.mb_substr($exception->getMessage(), 0, 3800),
                ])->save();
            }
        }
    }

    public function createTemplate(WhatsAppBusinessAccount $account, array $data): WhatsAppTemplate
    {
        $components = $this->templateComponents($data['body'], $data['sample_values'] ?? []);
        $response = $this->meta->createTemplate($account->waba_id, (string) $account->access_token, [
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => strtoupper($data['category']),
            'components' => $components,
        ]);

        return WhatsAppTemplate::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'provider_template_id' => $response['id'] ?? null,
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => strtolower((string) ($response['category'] ?? $data['category'])),
            'status' => strtolower((string) ($response['status'] ?? 'pending')),
            'components' => $components,
            'metadata' => $response,
            'last_synced_at' => now(),
        ]);
    }

    public function updateTemplate(WhatsAppBusinessAccount $account, WhatsAppTemplate $template, array $data): WhatsAppTemplate
    {
        abort_unless($template->whatsapp_business_account_id === $account->id, 404);
        abort_unless($template->provider_template_id, 422, 'Sync this template with Meta before editing it.');
        $components = $this->templateComponents($data['body'], $data['sample_values'] ?? []);
        $response = $this->meta->updateTemplate(
            $template->provider_template_id,
            (string) $account->access_token,
            [
                'category' => strtoupper($data['category']),
                'components' => $components,
            ],
        );
        $template->forceFill([
            'category' => strtolower((string) ($response['category'] ?? $data['category'])),
            'status' => strtolower((string) ($response['status'] ?? 'pending')),
            'components' => $components,
            'metadata' => [...($template->metadata ?? []), ...$response],
            'last_synced_at' => now(),
        ])->save();

        return $template;
    }

    private function templateComponents(string $body, array $sampleValues): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
        $indexes = array_values(array_unique(array_map('intval', $matches[1] ?? [])));
        if ($indexes !== [] && $indexes !== range(1, max($indexes))) {
            throw ValidationException::withMessages([
                'body' => ['Template variables must be sequential: {{1}}, {{2}}, and so on.'],
            ]);
        }
        if (count($sampleValues) < count($indexes)) {
            throw ValidationException::withMessages([
                'sample_values' => ['Provide one sample value for every template variable.'],
            ]);
        }
        $bodyComponent = ['type' => 'BODY', 'text' => $body];
        if ($indexes !== []) {
            $bodyComponent['example'] = ['body_text' => [array_slice(array_values($sampleValues), 0, count($indexes))]];
        }

        return [$bodyComponent];
    }
}
