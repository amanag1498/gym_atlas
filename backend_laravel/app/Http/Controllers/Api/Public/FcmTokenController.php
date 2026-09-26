<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\UserFcmToken;
use App\Services\Privacy\ConsentService;
use App\Services\Users\AppPresenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FcmTokenController extends Controller
{
    public function __construct(private readonly AppPresenceService $appPresenceService) {}

    public function store(Request $request)
    {
        app(ConsentService::class)->assertGranted($request->user(), 'notifications');
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:40'],
            'app_role' => ['nullable', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'device_key' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:80'],
        ]);

        $tokenUpdates = [
            'user_id' => $request->user()->id,
            'platform' => $validated['platform'] ?? null,
            'app_role' => $validated['app_role'] ?? $request->user()->active_role,
            'device_name' => $validated['device_name'] ?? null,
            'last_seen_at' => now(),
        ];
        if (Schema::hasColumn('user_fcm_tokens', 'device_key')) {
            $tokenUpdates['device_key'] = $validated['device_id'] ?? $validated['device_key'] ?? null;
        }
        if (Schema::hasColumn('user_fcm_tokens', 'app_version')) {
            $tokenUpdates['app_version'] = $validated['app_version'] ?? null;
        }
        if (Schema::hasColumn('user_fcm_tokens', 'uninstall_suspected_at')) {
            $tokenUpdates['uninstall_suspected_at'] = null;
        }
        if (Schema::hasColumn('user_fcm_tokens', 'revoked_at')) {
            $tokenUpdates['revoked_at'] = null;
        }

        $deviceKey = $tokenUpdates['device_key'] ?? null;
        $appRole = $tokenUpdates['app_role'];
        $token = DB::transaction(function () use ($validated, $tokenUpdates, $deviceKey, $appRole, $request): UserFcmToken {
            if (is_string($deviceKey) && $deviceKey !== '') {
                $staleDeviceTokens = UserFcmToken::query()
                    ->where('app_role', $appRole)
                    ->where(function ($query) use ($deviceKey, $request, $tokenUpdates): void {
                        $query->where('device_key', $deviceKey)
                            ->orWhere(function ($legacy) use ($request, $tokenUpdates): void {
                                $legacy->whereNull('device_key')
                                    ->where('user_id', $request->user()->id)
                                    ->where('platform', $tokenUpdates['platform'])
                                    ->where('device_name', $tokenUpdates['device_name']);
                            });
                    })
                    ->where('token', '!=', $validated['token']);

                if (Schema::hasColumn('user_fcm_tokens', 'revoked_at')) {
                    $staleDeviceTokens->update(['revoked_at' => now()]);
                } else {
                    $staleDeviceTokens->delete();
                }
            }

            return UserFcmToken::query()->updateOrCreate([
                'token' => $validated['token'],
            ], $tokenUpdates);
        });

        $presence = $this->appPresenceService->recordSeen($request->user(), $validated + [
            'token' => $validated['token'],
        ]);

        return $this->success([
            'id' => $token->id,
            'token' => $token->token,
            'platform' => $token->platform,
            'app_role' => $token->app_role,
            'last_seen_at' => $token->last_seen_at?->toIso8601String(),
            'presence_id' => $presence?->id,
        ], 'FCM token registered successfully.');
    }

    public function presence(Request $request)
    {
        $validated = $request->validate([
            'platform' => ['nullable', 'string', 'max:40'],
            'app_role' => ['nullable', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'device_key' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:80'],
        ]);

        $presence = $this->appPresenceService->recordSeen($request->user(), $validated);

        return $this->success([
            'presence_id' => $presence?->id,
            'app_role' => $presence?->app_role ?? ($validated['app_role'] ?? $request->user()->active_role),
            'platform' => $presence?->platform ?? ($validated['platform'] ?? null),
            'last_seen_at' => $presence?->last_seen_at?->toIso8601String(),
        ], 'App presence recorded successfully.');
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'app_role' => ['nullable', 'string', 'max:40'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'device_key' => ['nullable', 'string', 'max:120'],
        ]);

        $tokenQuery = UserFcmToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $validated['token']);
        if (Schema::hasColumn('user_fcm_tokens', 'revoked_at')) {
            $tokenQuery->update(['revoked_at' => now()]);
        } else {
            $tokenQuery->delete();
        }

        $this->appPresenceService->markRevoked($request->user(), $validated);

        return $this->success(null, 'FCM token removed successfully.');
    }
}
