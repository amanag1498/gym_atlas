<?php

namespace App\Services\WhatsApp;

use App\Enums\RoleName;
use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppBusinessAccount;
use App\Models\WhatsAppOnboardingSession;
use App\Services\Authorization\ScopeResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppOnboardingService
{
    public function __construct(
        private readonly WhatsAppConnectionService $connections,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    /** @return array{url:string,expires_at:string} */
    public function start(?Gym $gym, User $actor): array
    {
        WhatsAppOnboardingSession::query()
            ->where('created_by_user_id', $actor->id)
            ->where('gym_id', $gym?->id)
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere(function ($processing): void {
                        $processing->where('status', 'processing')
                            ->where('updated_at', '<=', now()->subMinutes(5));
                    });
            })
            ->update(['status' => 'superseded']);

        $token = Str::random(64);
        $expiresAt = now()->addMinutes(20);
        WhatsAppOnboardingSession::query()->create([
            'token_hash' => hash('sha256', $token),
            'gym_id' => $gym?->id,
            'created_by_user_id' => $actor->id,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        return [
            'url' => route('whatsapp.onboarding.show', ['token' => $token]),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function resolve(string $token): WhatsAppOnboardingSession
    {
        return WhatsAppOnboardingSession::query()
            ->with(['gym', 'creator'])
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }

    public function complete(string $token, string $code, string $wabaId, string $phoneNumberId): WhatsAppBusinessAccount
    {
        $session = DB::transaction(function () use ($token): WhatsAppOnboardingSession {
            $session = WhatsAppOnboardingSession::query()
                ->lockForUpdate()
                ->where('token_hash', hash('sha256', $token))
                ->first();
            if (! $session || $session->status !== 'pending' || $session->expires_at->isPast()) {
                throw ValidationException::withMessages(['session' => ['This connection session expired or was already used.']]);
            }
            $actor = $session->creator;
            $authorized = $actor?->is_active === true && ($session->gym_id
                ? $this->scopeResolver->canAccessGym($actor, $session->gym_id)
                : $actor->hasRole(RoleName::PlatformAdmin->value));
            if (! $authorized) {
                throw ValidationException::withMessages(['session' => ['Your account no longer has access to this communication scope.']]);
            }
            $session->forceFill(['status' => 'processing', 'last_error' => null])->save();

            return $session->load(['gym', 'creator']);
        });

        try {
            $account = $this->connections->connect($session->gym, $session->creator, $code, $wabaId, $phoneNumberId);
            $session->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

            return $account;
        } catch (Throwable $exception) {
            $session->forceFill([
                'status' => 'pending',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();
            throw $exception;
        }
    }
}
