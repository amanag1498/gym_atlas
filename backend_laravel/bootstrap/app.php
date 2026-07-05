<?php

use App\Enums\RoleName;
use App\Http\Middleware\EnsureActiveRole;
use App\Http\Middleware\EnsureBranchScope;
use App\Http\Middleware\EnsureGymScope;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureWebGymPanelAccess;
use App\Http\Middleware\EnsureWebPlatformAdmin;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(static function (Request $request): string {
            if ($request->is('gym') || $request->is('gym/*')) {
                return route('web.gym.login');
            }

            return route('web.admin.login');
        });

        $middleware->redirectUsersTo(static function (Request $request): string {
            $user = $request->user();

            if (! $user) {
                return route('web.admin.login');
            }

            if ($request->is('admin/login') || $request->is('admin/login/*') || $request->is('admin')) {
                if ($user->hasRole(RoleName::PlatformAdmin->value)) {
                    return route('web.admin.dashboard');
                }

                $gymId = $user->gyms()->orderBy('gyms.id')->value('gyms.id');

                return $gymId
                    ? route('web.gym.dashboard', ['gym' => $gymId])
                    : route('web.gym.login');
            }

            if ($request->is('gym/login') || $request->is('gym/login/*') || $request->is('gym')) {
                if ($user->hasRole(RoleName::PlatformAdmin->value)) {
                    return route('web.admin.dashboard');
                }

                $gymId = $user->gyms()->orderBy('gyms.id')->value('gyms.id');

                return $gymId
                    ? route('web.gym.dashboard', ['gym' => $gymId])
                    : route('web.admin.login');
            }

            return route('web.admin.dashboard');
        });

        $middleware->alias([
            'active_role' => EnsureActiveRole::class,
            'branch_scope' => EnsureBranchScope::class,
            'gym_scope' => EnsureGymScope::class,
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
            'web_gym_panel' => EnsureWebGymPanelAccess::class,
            'web_platform_admin' => EnsureWebPlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $renderJson = function (Request $request): bool {
            return $request->is('api/*') || $request->expectsJson();
        };

        $exceptions->render(function (ValidationException $exception, Request $request) use ($renderJson) {
            if (! $renderJson($request)) {
                return null;
            }

            return ApiResponse::validationError($exception->errors(), 'Validation failed.');
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($renderJson) {
            if (! $renderJson($request)) {
                return null;
            }

            return ApiResponse::error('Unauthenticated.', 401);
        });

        $exceptions->render(function (UnauthorizedException|AuthorizationException $exception, Request $request) use ($renderJson) {
            if (! $renderJson($request)) {
                return null;
            }

            $message = $exception instanceof AuthorizationException
                ? 'You do not have the required permission.'
                : ($exception->getMessage() ?: 'Forbidden.');

            return ApiResponse::error($message, 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) use ($renderJson) {
            if (! $renderJson($request)) {
                return null;
            }

            return ApiResponse::error('Resource not found.', 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($renderJson) {
            if (! $renderJson($request)) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage() ?: 'Request failed.',
                $exception->getStatusCode(),
            );
        });

        $exceptions->render(function (\Throwable $exception, Request $request) use ($renderJson) {
            if (! $renderJson($request)) {
                return null;
            }

            $message = config('app.debug')
                ? $exception->getMessage()
                : 'Server error.';

            return ApiResponse::error($message, 500);
        });
    })->create();
