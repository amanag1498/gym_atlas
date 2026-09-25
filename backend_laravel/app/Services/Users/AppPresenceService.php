<?php

namespace App\Services\Users;

use App\Models\User;
use App\Models\UserAppPresence;
use App\Models\UserFcmToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppPresenceService
{
    public function isReady(): bool
    {
        try {
            return Schema::hasTable('user_app_presences')
                && Schema::hasColumn('user_app_presences', 'device_key')
                && Schema::hasColumn('user_app_presences', 'last_seen_at');
        } catch (Throwable) {
            return false;
        }
    }

    public function recordSeen(User $user, array $payload): ?UserAppPresence
    {
        if (! $this->isReady()) {
            return null;
        }
        $role = $this->normalizeRole($payload['app_role'] ?? $user->active_role ?? 'member');
        $deviceKey = $this->normalizeDeviceKey($payload['device_id'] ?? $payload['device_key'] ?? $payload['token'] ?? 'unknown');
        $now = now();

        /** @var UserAppPresence $presence */
        $presence = UserAppPresence::query()->firstOrNew([
            'user_id' => $user->id,
            'app_role' => $role,
            'device_key' => $deviceKey,
        ]);

        $presence->fill([
            'platform' => $this->nullableString($payload['platform'] ?? null, 40),
            'device_name' => $this->nullableString($payload['device_name'] ?? null, 255),
            'app_version' => $this->nullableString($payload['app_version'] ?? null, 80),
            'first_seen_at' => $presence->first_seen_at ?: $now,
            'last_seen_at' => $now,
            'uninstall_suspected_at' => null,
            'revoked_at' => null,
        ])->save();

        return $presence;
    }

    public function markRevoked(User $user, array $payload): void
    {
        if (! $this->isReady()) {
            return;
        }

        $role = $this->normalizeRole($payload['app_role'] ?? $user->active_role ?? 'member');
        $deviceKey = $this->normalizeDeviceKey($payload['device_id'] ?? $payload['device_key'] ?? $payload['token'] ?? 'unknown');

        UserAppPresence::query()
            ->where('user_id', $user->id)
            ->where('app_role', $role)
            ->where('device_key', $deviceKey)
            ->update(['revoked_at' => now()]);
    }

    public function markPushSuccess(string $token): void
    {
        if (! $this->isReady()) {
            return;
        }

        $fcmToken = UserFcmToken::query()->where('token', $token)->first();
        if (! $fcmToken) {
            return;
        }

        UserAppPresence::query()
            ->where('user_id', $fcmToken->user_id)
            ->where('app_role', $this->normalizeRole($fcmToken->app_role ?: 'member'))
            ->when($fcmToken->device_key, fn ($query) => $query->where('device_key', $fcmToken->device_key))
            ->update(['last_push_success_at' => now(), 'uninstall_suspected_at' => null]);
    }

    public function markUninstallSuspected(string $token): void
    {
        if (! $this->isReady()) {
            return;
        }

        $fcmToken = UserFcmToken::query()->where('token', $token)->first();
        if (! $fcmToken) {
            return;
        }

        UserAppPresence::query()
            ->where('user_id', $fcmToken->user_id)
            ->where('app_role', $this->normalizeRole($fcmToken->app_role ?: 'member'))
            ->when($fcmToken->device_key, fn ($query) => $query->where('device_key', $fcmToken->device_key))
            ->update(['uninstall_suspected_at' => now()]);
    }

    public function summary(User $user, string $role = 'member'): array
    {
        if (! $this->isReady()) {
            return $this->summaryFromPresences(collect());
        }

        $presences = $user->relationLoaded('appPresences')
            ? $user->appPresences->where('app_role', $role)->values()
            : UserAppPresence::query()->where('user_id', $user->id)->where('app_role', $role)->get();

        return $this->summaryFromPresences($presences);
    }

    /** @param Collection<int, UserAppPresence> $presences */
    public function summaryFromPresences(Collection $presences): array
    {
        $presences = $presences->sortByDesc(fn (UserAppPresence $presence) => $presence->last_seen_at?->timestamp ?? 0)->values();
        $currentPresences = $presences->whereNull('revoked_at')->values();
        $summaryPresences = $currentPresences->isNotEmpty() ? $currentPresences : $presences;
        $latest = $summaryPresences->first();
        $activeWindow = now()->subDays(30);

        if (! $latest) {
            return [
                'status' => 'unknown',
                'label' => 'Not using app yet',
                'tone' => 'warning',
                'description' => 'No member app check-in has been recorded.',
                'last_seen_at' => null,
                'device_count' => 0,
                'platforms' => [],
                'app_versions' => [],
                'latest' => null,
            ];
        }

        $latestSeen = $latest->last_seen_at;
        $allCurrentUninstallSuspected = $currentPresences->isNotEmpty()
            && $currentPresences->every(fn (UserAppPresence $presence): bool => $presence->uninstall_suspected_at !== null);

        if ($currentPresences->isEmpty()) {
            $status = 'signed_out';
            $label = 'Signed out';
            $tone = 'neutral';
            $description = 'The app was used before, but all known devices signed out.';
        } elseif ($allCurrentUninstallSuspected) {
            $status = 'uninstall_suspected';
            $label = 'Uninstall suspected';
            $tone = 'danger';
            $description = 'Push delivery failed for the known app device.';
        } elseif ($latestSeen instanceof Carbon && $latestSeen->greaterThanOrEqualTo($activeWindow)) {
            $status = 'active';
            $label = 'App active';
            $tone = 'success';
            $description = 'The member app checked in within the last 30 days.';
        } else {
            $status = 'inactive';
            $label = 'App inactive';
            $tone = 'warning';
            $description = 'The app was used before, but there has been no recent check-in.';
        }

        return [
            'status' => $status,
            'label' => $label,
            'tone' => $tone,
            'description' => $description,
            'last_seen_at' => $latestSeen,
            'device_count' => $summaryPresences->count(),
            'platforms' => $summaryPresences->pluck('platform')->filter()->unique()->values()->all(),
            'app_versions' => $summaryPresences->pluck('app_version')->filter()->unique()->values()->all(),
            'latest' => $latest,
        ];
    }

    private function normalizeRole(mixed $role): string
    {
        $role = trim((string) $role);

        return $role === '' ? 'member' : mb_substr($role, 0, 40);
    }

    private function normalizeDeviceKey(mixed $value): string
    {
        $value = trim((string) $value);

        return mb_substr($value === '' ? 'unknown' : $value, 0, 120);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
