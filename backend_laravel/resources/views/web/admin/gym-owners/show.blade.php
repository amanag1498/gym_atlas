@extends('layouts.panel')

@section('content')
    @section('page_actions')
        @if ($owner->owned_gyms_count > 0)
            <x-action-button as="a" href="{{ route('web.admin.gym-owners.dashboard', $owner) }}">Open Gym Panel</x-action-button>
        @endif
        <x-action-button as="a" href="{{ route('web.admin.gym-owners.edit', $owner) }}">Edit Owner</x-action-button>
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-owners.index') }}">Back to Owners</x-action-button>
    @endsection

    <div class="space-y-6">
        @if (session('owner_temp_password'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                Temporary password: <code>{{ session('owner_temp_password') }}</code>
            </div>
        @endif

        <section class="panel-hero">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap gap-2">
                        <x-status-badge :label="$owner->is_active ? 'Active' : 'Inactive'" :tone="$owner->is_active ? 'success' : 'danger'" />
                        <x-status-badge :label="$owner->owned_gyms_count.' gyms'" tone="info" />
                        <x-status-badge :label="$owner->active_owned_gyms_count.' active gyms'" :tone="$owner->active_owned_gyms_count > 0 ? 'warning' : 'neutral'" />
                    </div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $owner->name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $owner->email }}</p>
                    @if ($hasPhoneColumn && $owner->phone)
                        <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $owner->phone }}</p>
                    @endif
                </div>

                <div class="grid w-full gap-3 sm:grid-cols-2 xl:w-[360px]">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Created</div>
                        <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $owner->created_at?->format('d M Y') ?? 'N/A' }}</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Last Login</div>
                        <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $owner->last_login_at?->format('d M Y h:i A') ?? 'Never' }}</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Owned Gyms" :value="$owner->owned_gyms_count" hint="Total gyms owned" tone="sky" />
            <x-stat-card label="Active Gyms" :value="$owner->active_owned_gyms_count" hint="Operational gyms" tone="amber" />
            <x-stat-card label="Branches" :value="$owner->total_branches_count" hint="Branches across footprint" tone="violet" />
            <x-stat-card label="Members" :value="$owner->total_members_count" hint="Members across footprint" tone="emerald" />
        </div>

        <div class="space-y-6">
            <x-premium-card class="p-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-3xl">
                        <h3 class="panel-section-title">Owner Status</h3>
                        <p class="panel-section-copy">Platform-level controls for this owner without pushing the page into a wasted sidebar layout.</p>
                    </div>
                    <div class="w-full xl:max-w-md">
                        @if ($owner->is_active)
                            @if ($owner->active_owned_gyms_count > 0)
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                                    This owner currently controls {{ $owner->active_owned_gyms_count }} active gym(s). Deactivation requires explicit confirmation.
                                </div>
                                <form method="POST" action="{{ route('web.admin.gym-owners.deactivate', $owner) }}" class="mt-4 space-y-3" onsubmit="return confirm('Deactivate this owner even though they still control active gyms?');">
                                    @csrf
                                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-200">
                                        <input class="form-check-input mt-1" type="checkbox" name="confirm_orphan_active_gyms" value="1" required>
                                        <span>I understand this owner still controls active gyms.</span>
                                    </label>
                                    <x-action-button type="submit" variant="danger" class="w-full">Deactivate Owner</x-action-button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('web.admin.gym-owners.deactivate', $owner) }}" onsubmit="return confirm('Deactivate this gym owner?');">
                                    @csrf
                                    <x-action-button type="submit" variant="danger" class="w-full">Deactivate Owner</x-action-button>
                                </form>
                            @endif
                        @else
                            <form method="POST" action="{{ route('web.admin.gym-owners.activate', $owner) }}">
                                @csrf
                                <x-action-button type="submit" class="w-full">Activate Owner</x-action-button>
                            </form>
                        @endif
                    </div>
                </div>
            </x-premium-card>

            <x-table-wrapper class="overflow-hidden">
                <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h3 class="panel-section-title">Owned Gyms</h3>
                            <p class="panel-section-copy">Every gym mapped to this owner, with billing and activity context.</p>
                        </div>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.create') }}">Add Gym</x-action-button>
                    </div>
                </div>

                @if ($owner->ownedGyms->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="panel-table min-w-[1180px]">
                            <thead>
                                <tr>
                                    <th>Gym</th>
                                    <th>City</th>
                                    <th>Counts</th>
                                    <th>Platform Billing</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($owner->ownedGyms as $gym)
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $gym->name }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym->slug }}</div>
                                        </td>
                                        <td>{{ $gym->city ?: 'N/A' }}</td>
                                        <td class="text-sm text-slate-600 dark:text-slate-300">
                                            <div>Branches {{ $gym->branches_count }}</div>
                                            <div>Members {{ $gym->member_profiles_count }}</div>
                                            <div>Trainers {{ $gym->trainer_profiles_count }}</div>
                                        </td>
                                        <td>
                                            @if ($gym->currentPlatformSubscription)
                                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ $gym->currentPlatformSubscription->plan?->name ?? 'Custom billing' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">₹{{ number_format((float) $gym->currentPlatformSubscription->billing_amount, 0) }} • {{ $gym->currentPlatformSubscription->status }}</div>
                                            @else
                                                <span class="text-sm text-slate-500 dark:text-slate-400">Not assigned</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-2">
                                                <x-status-badge :label="$gym->approval_status ?: $gym->status ?: 'pending'" />
                                                <x-status-badge :label="$gym->is_active ? 'Active' : 'Inactive'" :tone="$gym->is_active ? 'success' : 'danger'" />
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.show', $gym) }}">View</x-action-button>
                                                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.create', ['gym' => $gym->id]) }}">Billing</x-action-button>
                                                <x-action-button as="a" href="{{ route('web.admin.gym-owners.gyms.dashboard', ['user' => $owner, 'gym' => $gym]) }}">Open Panel</x-action-button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-6">
                        <x-empty-state title="No gyms owned yet" message="This owner has not been assigned to any gyms." />
                    </div>
                @endif
            </x-table-wrapper>
        </div>

        @include('web.admin.partials.activity-log-section', [
            'title' => 'Owner Activity Intelligence',
            'description' => 'Recent ownership and gym-footprint activity, with the full audit ledger moved to a dedicated history page.',
            'activityStats' => $activityStats,
            'activityTimeline' => $activityTimeline,
            'activityRows' => $activityRows,
            'activityLatestLabel' => $activityLatestLabel,
            'historyUrl' => route('web.admin.gym-owners.activity', $owner),
            'emptyTitle' => 'No owner activity yet',
            'emptyMessage' => 'Owner and footprint audit history will appear here once actions are recorded.',
        ])
    </div>
@endsection
