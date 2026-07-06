@php
    $currentGym = $panelContext['current_gym'] ?? null;
    $branches = $panelContext['branches'] ?? collect();
    $currentBranch = $panelContext['current_branch'] ?? null;
    $panel = $panelContext['panel'] ?? 'admin';
    $user = $panelContext['user'] ?? null;
    $userName = $user?->name ?: 'Panel User';
    $userInitial = strtoupper(substr($userName, 0, 1));
@endphp

<div class="flex flex-col gap-2 px-4 py-3 sm:px-6 lg:px-8 xl:flex-row xl:items-center xl:justify-between">
    <div class="flex min-w-0 items-center gap-3">
        <button type="button" id="sidebar-toggle-desktop" class="hidden h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white xl:inline-flex">
            <i class="ti ti-layout-sidebar text-base"></i>
        </button>

        <button type="button" id="sidebar-toggle-mobile" onclick="document.body.classList.add('panel-sidebar-mobile-open')" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white xl:hidden">
            <i class="ti ti-menu-2 text-base"></i>
        </button>

        <div class="min-w-0">
            @if (!empty($breadcrumbs))
                <div class="mb-1 flex flex-wrap items-center gap-1 text-[10px] font-medium uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">
                    @foreach ($breadcrumbs as $crumb)
                        <span>{{ $crumb }}</span>
                        @if (! $loop->last)
                            <i class="ti ti-chevron-right text-[10px]"></i>
                        @endif
                    @endforeach
                </div>
            @endif
            <h1 class="truncate text-[1.45rem] font-semibold tracking-tight text-gray-950 dark:text-white">{{ $pageTitle }}</h1>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" id="theme-toggle" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Toggle theme">
            <i class="ti ti-sun hidden text-base dark:inline-block"></i>
            <i class="ti ti-moon text-base dark:hidden"></i>
        </button>

        @hasSection('page_actions')
            <div class="flex flex-wrap items-center gap-2">
                @yield('page_actions')
            </div>
        @endif

        @if ($panel === 'gym')
            <form method="GET" class="hidden items-center gap-2 xl:flex">
                <label class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-500">
                    <span>Gym</span>
                    <select name="gym" class="panel-topbar-select !h-8 !min-w-[160px] !rounded-lg !border-0 !bg-transparent !px-0 !py-0 !text-xs !shadow-none" onchange="this.form.submit()">
                        @foreach (($panelContext['gyms'] ?? collect()) as $gymOption)
                            <option value="{{ $gymOption->id }}" @selected($currentGym?->id === $gymOption->id)>{{ $gymOption->name }}</option>
                        @endforeach
                    </select>
                </label>

                @if ($branches->isNotEmpty())
                    <label class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-500">
                        <span>Branch</span>
                        <select name="branch" class="panel-topbar-select !h-8 !min-w-[160px] !rounded-lg !border-0 !bg-transparent !px-0 !py-0 !text-xs !shadow-none" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach ($branches as $branchOption)
                                <option value="{{ $branchOption->id }}" @selected($currentBranch?->id === $branchOption->id)>{{ $branchOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </form>
        @endif

        <div class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-2.5 py-2 dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-500 text-[11px] font-semibold text-white">{{ $userInitial }}</span>
            <div class="hidden min-w-0 sm:block">
                <div class="truncate text-[11px] font-medium text-gray-900 dark:text-white">{{ $userName }}</div>
            </div>
            <form method="POST" action="{{ route('web.logout') }}">
                @csrf
                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Logout">
                    <i class="ti ti-logout text-sm"></i>
                </button>
            </form>
        </div>
    </div>
</div>
