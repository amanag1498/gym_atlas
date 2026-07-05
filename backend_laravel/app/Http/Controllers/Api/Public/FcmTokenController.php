<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\UserFcmToken;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:40'],
            'app_role' => ['nullable', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $token = UserFcmToken::query()->updateOrCreate([
            'token' => $validated['token'],
        ], [
            'user_id' => $request->user()->id,
            'platform' => $validated['platform'] ?? null,
            'app_role' => $validated['app_role'] ?? $request->user()->active_role,
            'device_name' => $validated['device_name'] ?? null,
            'last_seen_at' => now(),
        ]);

        return $this->success([
            'id' => $token->id,
            'token' => $token->token,
            'platform' => $token->platform,
            'app_role' => $token->app_role,
            'last_seen_at' => $token->last_seen_at?->toIso8601String(),
        ], 'FCM token registered successfully.');
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        UserFcmToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return $this->success(null, 'FCM token removed successfully.');
    }
}
