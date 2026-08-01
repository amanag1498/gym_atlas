@props([
    'pageTitle' => config('app.name'),
    'panelContext' => [],
    'breadcrumbs' => [],
])

@php
    $panel = $panelContext['panel'] ?? 'admin';
    $currentGym = $panelContext['current_gym'] ?? null;
    $currentBranch = $panelContext['current_branch'] ?? null;
    $dashboardRoute = $panel === 'admin'
        ? route('web.admin.dashboard')
        : route('web.gym.dashboard', array_filter([
            'gym' => $currentGym?->id,
            'branch' => $currentBranch?->id,
        ]));
@endphp

<!doctype html>
<html lang="en" class="h-full">
<head>
    <title>{{ $pageTitle }} | {{ config('app.name') }}</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="description" content="Gym ecosystem control panel" />

    <link rel="icon" href="{{ asset('tailadmin/images/logo/logo-icon.svg') }}" type="image/svg+xml" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" />
    <link rel="stylesheet" href="{{ asset('tailadmin/fonts/tabler-icons.min.css') }}" />
    <script>
        (() => {
            const savedTheme = localStorage.getItem('gym-ecosystem-panel-theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (systemPrefersDark ? 'dark' : 'light');

            document.documentElement.classList.toggle('dark', theme === 'dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="panel-shell min-h-screen bg-gray-50 font-sans text-gray-800 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div id="panel-preloader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-white transition-opacity duration-300 dark:bg-gray-950">
        <div class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent"></div>
    </div>

    <div id="mobile-sidebar-backdrop" data-mobile-sidebar-backdrop class="fixed inset-0 z-40 hidden bg-gray-900/50 xl:hidden"></div>

    <div class="min-h-screen xl:flex">
        <x-admin.sidebar :panel-context="$panelContext" />

        <div id="app-main" class="flex min-h-screen min-w-0 flex-1 flex-col transition-all duration-300 xl:ml-[290px]">
            <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
                <x-admin.topbar
                    :panel-context="$panelContext"
                    :page-title="$pageTitle"
                    :breadcrumbs="$breadcrumbs"
                />
            </header>

            <main class="flex-1">
                <div class="mx-auto flex w-full max-w-[1680px] flex-col gap-6 px-4 py-4 sm:px-6 lg:px-8">
                    @if (session('web_panel.platform_admin_impersonator_id'))
                        <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-700 shadow-theme-xs md:flex-row md:items-center md:justify-between dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                            <div>
                                <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700 dark:text-slate-200">Gym workspace preview</div>
                                <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">This session uses the selected gym owner's permissions and gym scope.</div>
                            </div>
                            <form method="POST" action="{{ route('web.admin.impersonation.stop') }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                                    Exit Preview
                                </button>
                            </form>
                        </div>
                    @endif

                    <div class="admin-page-shell panel-reveal space-y-6">
                        <x-admin.alert />
                        <div class="admin-section-stack space-y-6">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </main>

            <footer class="border-t border-gray-200 bg-white/80 px-4 py-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900/80 dark:text-gray-400 sm:px-6 lg:px-8">
                <div class="mx-auto flex max-w-[1680px] flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <p class="m-0">© {{ date('Y') }} {{ config('app.name') }}</p>
                    <a href="{{ $dashboardRoute }}" class="font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                        Workspace
                    </a>
                </div>
            </footer>
        </div>
    </div>

    <x-admin.confirmation-modal />

    @stack('scripts')
</body>
</html>
