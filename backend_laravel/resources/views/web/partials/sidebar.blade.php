@php
    $panel = $panelContext['panel'] ?? 'admin';
    $user = $panelContext['user'] ?? null;
    $currentGym = $panelContext['current_gym'] ?? null;
    $currentBranch = $panelContext['current_branch'] ?? null;
    $gymQuery = array_filter([
        'gym' => $currentGym?->id,
        'branch' => $currentBranch?->id,
    ]);

    $homeRoute = $panel === 'admin'
        ? route('web.admin.dashboard')
        : route('web.gym.dashboard', $gymQuery);

    $groups = $panel === 'admin'
        ? [
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'ti-dashboard', 'route' => 'web.admin.dashboard', 'active' => ['web.admin.dashboard']],
                    ['label' => 'Gyms', 'icon' => 'ti-building', 'route' => 'web.admin.gyms.index', 'active' => ['web.admin.gyms.*']],
                    ['label' => 'Listings', 'icon' => 'ti-search', 'route' => 'web.admin.listings.index', 'active' => ['web.admin.listings.*']],
                    ['label' => 'Featured Gyms', 'icon' => 'ti-star', 'route' => 'web.admin.featured-gyms.index', 'active' => ['web.admin.featured-gyms.*']],
                    ['label' => 'Promoted Gyms', 'icon' => 'ti-bolt', 'route' => 'web.admin.promoted-gyms.index', 'active' => ['web.admin.promoted-gyms.*']],
                    ['label' => 'Platform Plans', 'icon' => 'ti-credit-card', 'route' => 'web.admin.platform-subscription-plans.index', 'active' => ['web.admin.platform-subscription-plans.*']],
                    ['label' => 'Gym Billing', 'icon' => 'ti-receipt-2', 'route' => 'web.admin.gym-platform-subscriptions.index', 'active' => ['web.admin.gym-platform-subscriptions.*']],
                ],
            ],
            [
                'label' => 'Inbox',
                'items' => [
                    ['label' => 'Frontend Enquiries', 'icon' => 'ti-mail', 'route' => 'web.admin.enquiries.index', 'active' => ['web.admin.enquiries.*']],
                ],
            ],
            [
                'label' => 'Users',
                'items' => [
                    ['label' => 'Users', 'icon' => 'ti-users', 'route' => 'web.admin.users.index', 'active' => ['web.admin.users.*']],
                    ['label' => 'Gym Owners', 'icon' => 'ti-building-store', 'route' => 'web.admin.gym-owners.index', 'active' => ['web.admin.gym-owners.*']],
                    ['label' => 'Trainers', 'icon' => 'ti-star', 'route' => 'web.admin.users.trainers', 'active' => ['web.admin.users.trainers']],
                    ['label' => 'Members', 'icon' => 'ti-users', 'route' => 'web.admin.users.members', 'active' => ['web.admin.users.members']],
                ],
            ],
            [
                'label' => 'Platform',
                'items' => [
                    ['label' => 'Facilities', 'icon' => 'ti-activity', 'route' => 'web.admin.facilities.index', 'active' => ['web.admin.facilities.*']],
                    ['label' => 'Exercise Book', 'icon' => 'ti-clipboard-list', 'route' => 'web.admin.exercises.index', 'active' => ['web.admin.exercises.*']],
                    ['label' => 'Workout Books', 'icon' => 'ti-book', 'route' => 'web.admin.workout-books.index', 'active' => ['web.admin.workout-books.*']],
                    ['label' => 'Banners', 'icon' => 'ti-photo', 'route' => 'web.admin.banners.index', 'active' => ['web.admin.banners.*']],
                    ['label' => 'Announcements', 'icon' => 'ti-speakerphone', 'route' => 'web.admin.announcements.index', 'active' => ['web.admin.announcements.*']],
                    ['label' => 'Notifications', 'icon' => 'ti-bell', 'route' => 'web.admin.notifications.index', 'active' => ['web.admin.notifications.*']],
                    ['label' => 'Fitness Goals', 'icon' => 'ti-target', 'route' => 'web.admin.fitness-goals.index', 'active' => ['web.admin.fitness-goals.*']],
                    ['label' => 'Trainer Specializations', 'icon' => 'ti-star', 'route' => 'web.admin.trainer-specializations.index', 'active' => ['web.admin.trainer-specializations.*']],
                    ['label' => 'Cities', 'icon' => 'ti-map-pin', 'route' => 'web.admin.cities.index', 'active' => ['web.admin.cities.*']],
                    ['label' => 'Reports', 'icon' => 'ti-report-analytics', 'route' => 'web.admin.reports.index', 'active' => ['web.admin.reports.*']],
                    ['label' => 'Settings', 'icon' => 'ti-settings', 'route' => 'web.admin.settings.index', 'active' => ['web.admin.settings.*']],
                    ['label' => 'Audit Logs', 'icon' => 'ti-history', 'route' => 'web.admin.audit-logs.index', 'active' => ['web.admin.audit-logs.*']],
                ],
            ],
        ]
        : [
            [
                'label' => 'Operations',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'ti-dashboard', 'route' => 'web.gym.dashboard', 'active' => ['web.gym.dashboard']],
                    ['label' => 'Members', 'icon' => 'ti-users', 'route' => 'web.gym.members.index', 'active' => ['web.gym.members.*', 'web.gym.memberships.*']],
                    ['label' => 'Memberships', 'icon' => 'ti-id', 'route' => 'web.gym.memberships.index', 'active' => ['web.gym.memberships.*']],
                    ['label' => 'Custom Fees', 'icon' => 'ti-discount-2', 'route' => 'web.gym.custom-fees.index', 'active' => ['web.gym.custom-fees.*', 'web.gym.members.custom-fee*']],
                    ['label' => 'Payments', 'icon' => 'ti-cash-banknote', 'route' => 'web.gym.payments.index', 'active' => ['web.gym.payments.*']],
                    ['label' => 'Attendance', 'icon' => 'ti-scan', 'route' => 'web.gym.attendance.index', 'active' => ['web.gym.attendance.*']],
                ],
            ],
            [
                'label' => 'People',
                'items' => [
                    ['label' => 'Branches', 'icon' => 'ti-building-community', 'route' => 'web.gym.branches.index', 'active' => ['web.gym.branches.*']],
                    ['label' => 'Trainers', 'icon' => 'ti-star', 'route' => 'web.gym.trainers.index', 'active' => ['web.gym.trainers.*']],
                    ['label' => 'Staff', 'icon' => 'ti-users-group', 'route' => 'web.gym.staff.index', 'active' => ['web.gym.staff.*']],
                    ['label' => 'Membership Plans', 'icon' => 'ti-id', 'route' => 'web.gym.membership-plans.index', 'active' => ['web.gym.membership-plans.*']],
                ],
            ],
            [
                'label' => 'Growth',
                'items' => [
                    ['label' => 'Announcements', 'icon' => 'ti-speakerphone', 'route' => 'web.gym.announcements.index', 'active' => ['web.gym.announcements.*']],
                    ['label' => 'Notifications', 'icon' => 'ti-bell', 'route' => 'web.gym.notifications.index', 'active' => ['web.gym.notifications.*']],
                    ['label' => 'Reminders', 'icon' => 'ti-clock', 'route' => 'web.gym.reminders.index', 'active' => ['web.gym.reminders.*']],
                    ['label' => 'Trial Requests', 'icon' => 'ti-target', 'route' => 'web.gym.trial-requests.index', 'active' => ['web.gym.trial-requests.*', 'web.gym.leads.*']],
                    ['label' => 'Reports', 'icon' => 'ti-report-analytics', 'route' => 'web.gym.reports.index', 'active' => ['web.gym.reports.*']],
                    ['label' => 'Settings', 'icon' => 'ti-settings', 'route' => 'web.gym.settings.index', 'active' => ['web.gym.settings.*']],
                    ['label' => 'Audit Logs', 'icon' => 'ti-history', 'route' => 'web.gym.audit-logs.index', 'active' => ['web.gym.audit-logs.*']],
                    ['label' => 'Public Listing', 'icon' => 'ti-world', 'route' => 'web.gym.public-listing.edit', 'active' => ['web.gym.public-listing.*']],
                    ['label' => 'Gym Profile', 'icon' => 'ti-building-store', 'route' => 'web.gym.profile.edit', 'active' => ['web.gym.profile.*']],
                ],
            ],
        ];
@endphp

<aside id="app-sidebar" class="fixed left-0 top-0 z-[60] flex h-screen w-[290px] -translate-x-full flex-col border-r border-gray-200 bg-white px-5 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900 xl:translate-x-0">
    <div id="app-sidebar-brand" class="flex items-center justify-between pb-7 pt-8">
        <a href="{{ $homeRoute }}">
            <x-layout.brand-mark :title="$panel === 'admin' ? 'Gym Atlas' : config('app.name')" :subtitle="$panel === 'admin' ? 'Platform Admin' : 'Gym Workspace'" />
        </a>

        <button type="button" id="sidebar-close-mobile" onclick="document.body.classList.remove('panel-sidebar-mobile-open')" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 dark:border-gray-800 dark:text-gray-400 xl:hidden">
            <i class="ti ti-x text-xl"></i>
        </button>
    </div>

    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto no-scrollbar">
        @if ($panel === 'gym' && $user?->hasRole(\App\Enums\RoleName::PlatformAdmin->value))
            <a href="{{ route('web.admin.gym-owners.index') }}" class="sidebar-widget-shell mb-6 inline-flex items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 transition hover:border-brand-300 hover:text-brand-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:border-brand-500/40 dark:hover:text-brand-300">
                <i class="ti ti-arrow-left text-lg"></i>
                <span class="sidebar-label">Back to Platform Admin</span>
            </a>
        @endif

        <nav class="mb-6">
            <div class="flex flex-col gap-4">
                @foreach ($groups as $group)
                    <div>
                        <h2 class="sidebar-group-label mb-4 flex justify-start text-xs leading-5 font-medium uppercase tracking-[0.18em] text-gray-400">
                            <span>{{ $group['label'] }}</span>
                        </h2>

                        <ul class="flex flex-col gap-1">
                            @foreach ($group['items'] as $item)
                                @php
                                    $isActive = request()->routeIs(...($item['active'] ?? [$item['route']]));
                                    $target = $panel === 'gym' ? route($item['route'], $gymQuery) : route($item['route']);
                                @endphp
                                <li>
                                    <a href="{{ $target }}" class="group relative flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $isActive ? 'bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-gray-300' }}">
                                        <span class="{{ $isActive ? 'text-brand-500 dark:text-brand-400' : 'text-gray-500 dark:text-gray-400' }}">
                                            <i class="ti {{ $item['icon'] }} text-xl"></i>
                                        </span>
                                        <span class="sidebar-label">{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        <div class="sidebar-widget-shell mt-auto mb-8 w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-sm font-semibold text-brand-600 shadow-sm dark:bg-gray-900 dark:text-brand-300">
                    {{ str($user?->active_role ?? 'guest')->substr(0, 1)->upper() }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $panel === 'admin' ? 'Platform Workspace' : ($currentGym?->name ?? 'Gym Workspace') }}
                    </div>
                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ $panel === 'admin' ? 'Admin scope active' : ($currentBranch?->name ? $currentBranch->name.' branch active' : 'All accessible branches') }}
                    </div>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    {{ str($user?->active_role ?? 'guest')->replace('_', ' ')->title() }}
                </span>
                @if ($panel === 'gym' && $currentGym)
                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        {{ $currentGym->name }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</aside>
