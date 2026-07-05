@php
    $allErrors = $errors->all();
    $singleError = count($allErrors) === 1 ? $allErrors[0] : null;
    $isPanelLoginScreen = request()->routeIs('web.admin.login', 'web.gym.login');

    $errorHeading = 'Please review the highlighted fields';
    $errorSummary = null;

    if ($isPanelLoginScreen && $singleError) {
        if (str_contains($singleError, 'not permitted to access the requested web panel')) {
            $errorHeading = 'Access restricted';
            $errorSummary = 'This sign-in is valid, but the account does not have access to this panel.';
        } elseif (str_contains($singleError, 'Invalid email or password.')) {
            $errorHeading = 'Sign-in failed';
            $errorSummary = 'The credentials did not match an active account for this panel.';
        } else {
            $errorHeading = 'Unable to continue';
        }
    }
@endphp

@if (session('status'))
    <div class="panel-flash-success flex items-start gap-3 rounded-2xl border border-success-200 bg-success-50 px-4 py-4 text-success-900 shadow-theme-xs dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-100">
        <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-success-500 text-white">
            <i class="ti ti-check text-lg"></i>
        </span>
        <div>
            <div class="text-sm font-semibold uppercase tracking-[0.16em] text-success-700 dark:text-success-300">Success</div>
            <div class="mt-1 text-sm">{{ session('status') }}</div>
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="{{ $isPanelLoginScreen ? 'border-rose-200/70 bg-white/88 text-slate-900 shadow-[0_18px_50px_rgba(15,23,42,0.08)] backdrop-blur-xl dark:border-rose-500/20 dark:bg-slate-950/65 dark:text-white' : 'border-error-200 bg-error-50 text-error-900 shadow-theme-xs dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-100' }} rounded-[1.35rem] border px-4 py-4">
        <div class="flex items-start gap-3">
            <span class="{{ $isPanelLoginScreen ? 'bg-gradient-to-br from-rose-500 to-orange-400 text-white shadow-[0_14px_30px_rgba(244,63,94,0.28)]' : 'bg-error-500 text-white' }} mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-full">
                <span class="text-lg font-semibold">!</span>
            </span>
            <div class="min-w-0">
                <div class="{{ $isPanelLoginScreen ? 'text-slate-900 dark:text-white' : 'text-error-700 dark:text-error-300' }} text-sm font-semibold uppercase tracking-[0.16em]">
                    {{ $errorHeading }}
                </div>
                @if ($errorSummary)
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-300">{{ $errorSummary }}</p>
                @endif

                @if ($singleError)
                    <div class="mt-2 text-sm leading-6 {{ $isPanelLoginScreen ? 'text-slate-700 dark:text-slate-200' : '' }}">
                        {{ $singleError }}
                    </div>
                @else
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($allErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endif
