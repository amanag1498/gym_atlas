<?php

namespace App\Services\WhatsApp;

use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppConsent;
use App\Support\CommunicationScope;
use Illuminate\Validation\ValidationException;

class WhatsAppConsentService
{
    public function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $hasInternationalPrefix = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (! $hasInternationalPrefix && strlen($digits) === 10) {
            $digits = trim((string) config('services.meta_whatsapp.default_country_code', '91')).$digits;
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
    }

    public function set(User $user, ?Gym $gym, string $purpose, bool $granted, array $evidence = []): WhatsAppConsent
    {
        $phone = $this->normalizePhone($user->phone);
        if (! $phone) {
            throw ValidationException::withMessages([
                'phone' => ['Add a valid mobile number before enabling WhatsApp notifications.'],
            ]);
        }

        return WhatsAppConsent::query()->updateOrCreate([
            'user_id' => $user->id,
            'gym_id' => $gym?->id,
            'scope_key' => CommunicationScope::key($gym?->id),
            'purpose' => $purpose,
        ], [
            'status' => $granted ? 'granted' : 'revoked',
            'phone_e164' => $phone,
            'source' => (string) ($evidence['consent_source'] ?? 'member_app'),
            'wording_version' => 'whatsapp-consent-v1',
            'evidence' => $evidence,
            'granted_at' => $granted ? now() : null,
            'revoked_at' => $granted ? null : now(),
        ]);
    }

    public function granted(User $user, ?int $gymId, string $purpose): ?WhatsAppConsent
    {
        return WhatsAppConsent::query()
            ->where('user_id', $user->id)
            ->where('gym_id', $gymId)
            ->where('purpose', $purpose)
            ->where('status', 'granted')
            ->first();
    }
}
