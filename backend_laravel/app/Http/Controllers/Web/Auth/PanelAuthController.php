<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\PanelFirebaseLoginRequest;
use App\Http\Requests\Web\Auth\PanelLoginRequest;
use App\Models\User;
use App\Services\Auth\FirebaseAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PanelAuthController extends Controller
{
    public function __construct(
        private readonly FirebaseAuthService $firebaseAuthService,
    ) {
    }

    public function showAdminLogin(): View
    {
        return view('web.auth.login', [
            'panel' => 'admin',
            'title' => 'Platform Admin Login',
            'subtitle' => 'Secure access for platform operations, listing approvals, and global reporting.',
            'firebaseConfig' => $this->firebaseWebConfig(),
        ]);
    }

    public function showGymLogin(): View
    {
        return view('web.auth.login', [
            'panel' => 'gym',
            'title' => 'Gym Admin Login',
            'subtitle' => 'Manage your gym operations, members, billing, attendance, and trials.',
            'firebaseConfig' => $this->firebaseWebConfig(),
        ]);
    }

    public function loginAdmin(PanelLoginRequest $request): RedirectResponse
    {
        return $this->attemptLogin($request, 'admin');
    }

    public function loginGym(PanelLoginRequest $request): RedirectResponse
    {
        return $this->attemptLogin($request, 'gym');
    }

    public function loginAdminWithFirebase(PanelFirebaseLoginRequest $request): RedirectResponse
    {
        return $this->attemptFirebaseLogin($request, 'admin');
    }

    public function loginGymWithFirebase(PanelFirebaseLoginRequest $request): RedirectResponse
    {
        return $this->attemptFirebaseLogin($request, 'gym');
    }

    public function logout(): RedirectResponse
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('web.admin.login');
    }

    private function attemptLogin(PanelLoginRequest $request, string $panel): RedirectResponse
    {
        $credentials = $request->validated();
        $remember = (bool) ($credentials['remember'] ?? false);

        if (! Auth::guard('web')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $remember)) {
            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->withInput($request->safe()->except('password'));
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        return $this->completePanelLogin($request, $user, $panel);
    }

    private function attemptFirebaseLogin(PanelFirebaseLoginRequest $request, string $panel): RedirectResponse
    {
        try {
            $user = $this->firebaseAuthService->authenticateForWeb(
                $request->validated('id_token'),
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput();
        }

        Auth::guard('web')->login($user, (bool) $request->boolean('remember'));

        return $this->completePanelLogin($request, $user, $panel);
    }

    private function completePanelLogin(PanelLoginRequest|PanelFirebaseLoginRequest $request, User $user, string $panel): RedirectResponse
    {
        $allowed = $panel === 'admin'
            ? $user->hasRole(RoleName::PlatformAdmin->value)
            : $user->hasAnyRole([
                RoleName::GymOwner->value,
                RoleName::BranchManager->value,
                RoleName::GymStaff->value,
            ]);

        if (! $allowed || in_array($user->active_role, [RoleName::Member->value, RoleName::Trainer->value], true)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'This account is not permitted to access the requested web panel.'])
                ->withInput($request->safe()->except('password'));
        }

        if ($panel === 'admin' && $user->hasRole(RoleName::PlatformAdmin->value)) {
            $user->forceFill(['active_role' => RoleName::PlatformAdmin->value])->save();
        }

        if ($panel === 'gym') {
            foreach ([RoleName::GymOwner, RoleName::BranchManager, RoleName::GymStaff] as $role) {
                if ($user->hasRole($role->value)) {
                    $user->forceFill(['active_role' => $role->value])->save();
                    break;
                }
            }
        }

        $request->session()->regenerate();
        $request->session()->forget('web_panel');

        return redirect()->route($panel === 'admin' ? 'web.admin.dashboard' : 'web.gym.dashboard');
    }

    /**
     * @return array<string, string>|null
     */
    private function firebaseWebConfig(): ?array
    {
        $projectId = (string) config('services.firebase.project_id');
        $apiKey = (string) config('services.firebase.web_api_key');
        $appId = (string) config('services.firebase.web_app_id');
        $messagingSenderId = (string) config('services.firebase.messaging_sender_id');

        if ($projectId === '' || $apiKey === '' || $appId === '' || $messagingSenderId === '') {
            return null;
        }

        $authDomain = (string) config('services.firebase.auth_domain');
        $storageBucket = (string) config('services.firebase.storage_bucket');

        return [
            'apiKey' => $apiKey,
            'authDomain' => $authDomain !== '' ? $authDomain : "{$projectId}.firebaseapp.com",
            'projectId' => $projectId,
            'storageBucket' => $storageBucket !== '' ? $storageBucket : "{$projectId}.appspot.com",
            'messagingSenderId' => $messagingSenderId,
            'appId' => $appId,
        ];
    }
}
