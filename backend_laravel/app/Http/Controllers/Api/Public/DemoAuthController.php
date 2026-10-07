<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\AuthSessionResource;
use App\Models\User;
use App\Services\Platform\PlatformSettingService;
use App\Services\Privacy\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DemoAuthController extends Controller
{
    public function __invoke(
        Request $request,
        PlatformSettingService $settings,
        ConsentService $consents,
    ) {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'app_type' => ['required', Rule::in([RoleName::Member->value, RoleName::Trainer->value, 'admin'])],
            'access_code' => ['required_if:app_type,admin', 'nullable', 'string', 'max:128'],
            'accepted_terms' => ['sometimes', 'boolean'],
            'enable_optional_features' => ['sometimes', 'boolean'],
        ]);

        $values = $settings->all();
        $appType = (string) $validated['app_type'];
        $adminDemo = $appType === 'admin';
        if (! (bool) ($values[$adminDemo ? 'demo_admin_login_enabled' : 'demo_login_enabled'] ?? false)) {
            return $this->unavailable();
        }

        $configuredEmailKey = $adminDemo
            ? 'demo_admin_login_email'
            : ($appType === RoleName::Trainer->value
                ? 'demo_trainer_login_email'
                : 'demo_member_login_email');
        $submittedEmail = mb_strtolower(trim((string) $validated['email']));
        $configuredEmail = mb_strtolower(trim((string) ($values[$configuredEmailKey] ?? '')));

        if ($configuredEmail === '' || ! hash_equals($configuredEmail, $submittedEmail)) {
            Log::warning('AUTH_DEMO_EMAIL_REJECTED', [
                'app_type' => $appType,
                'ip' => $request->ip(),
                'has_configured_email' => $configuredEmail !== '',
            ]);

            return $this->unavailable();
        }

        if ($adminDemo) {
            $codeHash = $settings->adminDemoCodeHash();
            if (! $codeHash || ! Hash::check((string) ($validated['access_code'] ?? ''), $codeHash)) {
                Log::warning('AUTH_DEMO_ADMIN_CODE_REJECTED', ['ip' => $request->ip()]);

                return $this->unavailable();
            }
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$configuredEmail])
            ->first();

        if (! $user || ! $user->hasRole($adminDemo ? RoleName::GymOwner->value : $appType)
            || ($adminDemo && ($user->hasRole(RoleName::PlatformAdmin->value)
                || $user->active_role !== RoleName::GymOwner->value))) {
            Log::warning('AUTH_DEMO_USER_UNAVAILABLE', [
                'app_type' => $appType,
                'has_user' => (bool) $user,
            ]);

            return $this->unavailable();
        }

        if (! $user->is_active) {
            try {
                $user->tokens()->delete();
            } catch (\Throwable) {
            }

            return $this->error('Your account is inactive. Please contact support.', 423, [
                'code' => 'demo_account_inactive',
            ]);
        }

        try {
            $user->forceFill(['last_login_at' => now()])->save();
            $user->tokens()->delete();
        } catch (\Throwable $error) {
            Log::warning('AUTH_DEMO_SESSION_SYNC_FAILED', [
                'user_id' => $user->id,
                'error' => $error->getMessage(),
            ]);
        }

        $deviceName = trim((string) ($validated['device_name'] ?? 'flutter-demo')) ?: 'flutter-demo';
        if ($request->boolean('accepted_terms')) {
            $consents->recordLoginAcceptance(
                $user,
                $request,
                $request->boolean('enable_optional_features', true),
            );
        }
        $session = [
            'token' => $user->createToken($deviceName, ['role:'.($adminDemo ? RoleName::GymOwner->value : $appType)])->plainTextToken,
            'user' => $user->fresh(['roles', 'permissions']),
        ];

        Log::info('AUTH_DEMO_LOGIN_SUCCESS', [
            'user_id' => $user->id,
            'app_type' => $appType,
            'device_name' => $deviceName,
        ]);

        return $this->success(AuthSessionResource::make($session), 'Demo login successful.');
    }

    private function unavailable()
    {
        return $this->error('Demo login is unavailable.', 403, [
            'code' => 'demo_login_unavailable',
        ]);
    }
}
