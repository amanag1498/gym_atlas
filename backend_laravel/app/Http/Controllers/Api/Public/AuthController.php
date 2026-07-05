<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FirebaseLoginRequest;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\User\SwitchActiveRoleRequest;
use App\Http\Resources\Auth\AuthSessionResource;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\Auth\FirebaseAuthService;
use App\Services\Auth\GoogleAuthService;
use App\Services\Authorization\ActiveRoleManager;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthService $googleAuthService,
        private readonly FirebaseAuthService $firebaseAuthService,
        private readonly ActiveRoleManager $activeRoleManager,
    ) {
    }

    public function googleLogin(GoogleLoginRequest $request)
    {
        $session = $this->googleAuthService->authenticate(
            idToken: $request->validated('id_token'),
            deviceName: $request->validated('device_name', 'flutter-app'),
        );

        return $this->success(
            AuthSessionResource::make($session),
            'Google login successful.',
        );
    }

    public function firebaseLogin(FirebaseLoginRequest $request)
    {
        $session = $this->firebaseAuthService->authenticate(
            idToken: $request->validated('id_token'),
            deviceName: $request->validated('device_name', 'flutter-app'),
        );

        return $this->success(
            AuthSessionResource::make($session),
            'Firebase login successful.',
        );
    }

    public function me(Request $request)
    {
        return $this->success(UserResource::make($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    public function switchActiveRole(SwitchActiveRoleRequest $request)
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('updateActiveRole', $user);

        $user = $this->activeRoleManager->setActiveRole(
            $user,
            $request->validated('active_role'),
        );

        return $this->success(
            UserResource::make($user->fresh(['roles', 'permissions'])),
            'Active role updated successfully.',
        );
    }
}
