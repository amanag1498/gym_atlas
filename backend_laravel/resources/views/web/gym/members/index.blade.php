@extends('layouts.panel')

@section('content')
    @php
        $memberCollection = $members->getCollection();
        $highRiskCount = $memberCollection->filter(fn ($member) => (($member->engagement_score['category'] ?? $member->memberProfile?->engagement_score['category'] ?? null) === 'High Risk'))->count();
        $noTrainerCount = $memberCollection->filter(fn ($member) => blank($member->memberProfile?->assignedTrainer?->name))->count();
        $expiringSoonCount = $memberCollection->filter(fn ($member) => in_array(strtolower((string) ($member->memberProfile?->membership_status ?? '')), ['expiring soon', 'expiring_soon'], true))->count();
        $dueMembersCount = $memberCollection->filter(fn ($member) => (float) ($member->memberMemberships->first()?->due_amount ?? 0) > 0)->count();
        $advancedFiltersActive = request()->filled('trainer_id')
            || request()->filled('plan_id')
            || request()->filled('gender')
            || request()->filled('goal')
            || request()->boolean('no_trainer_assigned')
            || request()->boolean('inactive_7_days');
    @endphp

    <div class="space-y-4">
        <section class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-linear-to-br from-slate-950 via-slate-900 to-sky-950 text-white shadow-[0_24px_80px_-36px_rgba(15,23,42,0.75)] dark:border-slate-800">
            <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between lg:px-6">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Members</h1>
                    <p class="mt-1 text-sm text-slate-300">{{ $members->total() }} in this result</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-action-button as="a" href="{{ route('web.gym.members.create', request()->query()) }}">Add Member</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.self-enrollment.index', request()->query()) }}">Enrollment QR</x-action-button>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950 lg:grid-cols-5">
            @foreach ([
                ['label' => 'Members', 'value' => $members->total(), 'href' => route('web.gym.members.index', request()->only(['gym', 'branch']))],
                ['label' => 'Due', 'value' => $dueMembersCount, 'href' => request()->fullUrlWithQuery(['status' => 'due_payment'])],
                ['label' => 'High risk', 'value' => $highRiskCount, 'href' => null],
                ['label' => 'No trainer', 'value' => $noTrainerCount, 'href' => request()->fullUrlWithQuery(['no_trainer_assigned' => 1])],
                ['label' => 'Expiring', 'value' => $expiringSoonCount, 'href' => request()->fullUrlWithQuery(['status' => 'expiring_soon'])],
            ] as $metric)
                @if ($metric['href'])
                    <a href="{{ $metric['href'] }}" class="{{ $loop->last ? 'col-span-2 lg:col-span-1' : '' }} flex items-center justify-between border-b border-r border-slate-200/80 px-4 py-3 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/70 lg:border-b-0">
                @else
                    <div class="{{ $loop->last ? 'col-span-2 lg:col-span-1' : '' }} flex items-center justify-between border-b border-r border-slate-200/80 px-4 py-3 dark:border-slate-800 lg:border-b-0">
                @endif
                        <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{{ $metric['label'] }}</span>
                        <span class="text-lg font-semibold text-slate-950 dark:text-white">{{ $metric['value'] }}</span>
                @if ($metric['href'])
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>

        <div class="space-y-4">
            <x-premium-card class="overflow-hidden p-0">
                <form method="GET" class="space-y-4 px-5 py-4">
                    @if (request()->filled('gym'))
                        <input type="hidden" name="gym" value="{{ request('gym') }}">
                    @endif
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(280px,1.5fr)_minmax(180px,0.8fr)_minmax(180px,0.8fr)_auto]">
                        <x-form-input name="search" label="Search members" :value="request('search')" />
                        <x-form-select name="status" label="Status" :options="['' => 'All statuses', 'active' => 'Active', 'frozen' => 'Frozen', 'inactive' => 'Inactive', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'left_gym' => 'Left gym', 'expiring_soon' => 'Expiring Soon', 'due_payment' => 'Due Payment', 'overdue' => 'Overdue']" :selected="request('status')" />
                        <x-form-select name="branch_id" label="Branch" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" :selected="request('branch_id')" />
                        <div class="flex items-end gap-2">
                            <x-action-button type="submit">Apply</x-action-button>
                            <x-action-button as="a" variant="secondary" href="{{ route('web.gym.members.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                        </div>
                    </div>

                    <details class="group" @if ($advancedFiltersActive) open @endif>
                        <summary class="inline-flex cursor-pointer list-none items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">
                            Advanced filters
                            <span class="text-xs transition group-open:rotate-180">⌄</span>
                        </summary>
                        <div class="mt-3 grid gap-3 border-t border-slate-200/80 pt-4 md:grid-cols-2 xl:grid-cols-4 dark:border-slate-800">
                            <x-form-select name="trainer_id" label="Trainer" :options="['' => 'All trainers'] + $trainers->pluck('name', 'id')->all()" :selected="request('trainer_id')" />
                            <x-form-select name="plan_id" label="Plan" :options="['' => 'All plans'] + $plans->pluck('name', 'id')->all()" :selected="request('plan_id')" />
                            <x-form-select name="gender" label="Gender" :options="['' => 'All genders', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :selected="request('gender')" />
                            <x-form-input name="goal" label="Goal" :value="request('goal')" />
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input type="checkbox" name="no_trainer_assigned" value="1" @checked(request()->boolean('no_trainer_assigned'))>
                                No trainer assigned
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input type="checkbox" name="inactive_7_days" value="1" @checked(request()->boolean('inactive_7_days'))>
                                Inactive for 7 days
                            </label>
                        </div>
                    </details>
                </form>
            </x-premium-card>

            <div class="rounded-2xl border border-slate-200/80 bg-white px-3 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <div class="flex flex-wrap items-center gap-2">
                    <details class="group min-w-[12rem] flex-1">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl px-2 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-900">
                            Import CSV
                            <span class="text-xs text-slate-500 transition group-open:rotate-180 dark:text-slate-400">⌄</span>
                        </summary>
                        <form action="{{ route('web.gym.members.import.preview', request()->query()) }}" method="POST" enctype="multipart/form-data" class="mt-3 grid gap-3 border-t border-slate-200/80 px-2 pt-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end dark:border-slate-800">
                            @csrf
                            <x-form-input name="members_csv" label="CSV file" type="file" required />
                            <x-action-button type="submit">Preview Import</x-action-button>
                        </form>
                    </details>
                    <x-action-button as="a" variant="secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Export CSV</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.memberships.index', request()->query()) }}">Memberships</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.index', array_merge(request()->query(), ['report' => 'inactive_members'])) }}">Inactive Members</x-action-button>
                </div>
            </div>

            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Member list</h2>
                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ $members->total() }} results</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1480px]">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Membership</th>
                                    <th>Trainer / Branch</th>
                                    <th>Goal / Profile</th>
                                    <th>Engagement</th>
                                    <th>App</th>
                                    <th class="w-[25rem]">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($members as $member)
                                    @php
                                        $profile = $member->memberProfile;
                                        $hasOperationalAccess = $profile?->is_active && in_array($profile?->membership_status, ['active', 'frozen'], true);
                                        $memberStatusLabel = $profile?->membership_status === 'left_gym'
                                            ? 'Left gym'
                                            : str((string) ($profile?->membership_status ?? 'active'))->replace('_', ' ')->title();
                                        $latestMembership = $member->memberMemberships->first();
                                        $engagement = $member->engagement_score ?? $profile?->engagement_score ?? null;
                                        $engagementScore = (int) ($engagement['score'] ?? 0);
                                        $appPresence = ($memberAppPresenceSummaries ?? collect())->get($member->id, ['label' => 'Not using app yet', 'tone' => 'warning', 'last_seen_at' => null, 'platforms' => []]);
                                        $engagementToneClass = match ($engagement['category'] ?? null) {
                                            'Excellent' => 'bg-emerald-500',
                                            'Good' => 'bg-sky-500',
                                            'Needs Attention' => 'bg-amber-500',
                                            'High Risk' => 'bg-rose-500',
                                            default => 'bg-slate-400',
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="flex min-w-[14rem] items-center gap-3">
                                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-950 text-sm font-semibold text-white dark:bg-slate-800">
                                                    {{ strtoupper(substr($member->name, 0, 1)) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <a href="{{ route('web.gym.members.show', ['member' => $member->id] + request()->only(['gym', 'branch'])) }}" class="block truncate font-semibold text-slate-950 hover:text-brand-600 dark:text-white dark:hover:text-brand-300">{{ $member->name }}</a>
                                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $member->email }}</p>
                                                    @if (Schema::hasColumn('users', 'phone') && filled($member->phone))
                                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $member->phone }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="min-w-[13rem]">
                                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ $latestMembership?->membershipPlan?->name ?? 'No membership' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                    @if ($latestMembership)
                                                        Due ₹{{ number_format((float) $latestMembership->due_amount, 2) }} • {{ optional($latestMembership->expiry_date)->format('d M Y') ?: 'No expiry' }}
                                                    @else
                                                        Membership not assigned
                                                    @endif
                                                </div>
                                                <div class="mt-2 flex flex-wrap gap-1.5">
                                                    <x-status-badge :label="$memberStatusLabel" :tone="$profile?->membership_status === 'left_gym' ? 'neutral' : 'info'" />
                                                    @if ($latestMembership)
                                                        <x-status-badge :label="ucfirst((string) $latestMembership->payment_status)" :tone="match((string) $latestMembership->payment_status) { 'paid' => 'success', 'partial' => 'warning', 'overdue' => 'danger', default => 'neutral' }" />
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="min-w-[11rem] text-sm">
                                                <p class="font-medium text-slate-950 dark:text-white">{{ $profile?->assignedTrainer?->name ?? 'Unassigned' }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $profile?->branch?->name ?? 'Branch missing' }}</p>
                                                @if ($profile?->emergency_contact_name)
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Emergency: {{ $profile->emergency_contact_name }}</p>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="max-w-[12rem] text-sm text-slate-700 dark:text-slate-300">
                                                <p class="truncate">{{ $profile?->fitness_goal ?: 'Not set' }}</p>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $profile?->height_cm ? $profile->height_cm.' cm' : 'No height' }}
                                                    •
                                                    {{ $profile?->weight_kg ? $profile->weight_kg.' kg' : 'No weight' }}
                                                </p>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="min-w-[10rem]">
                                                <div class="flex items-center justify-between gap-2 text-xs">
                                                    <span class="font-semibold text-slate-950 dark:text-white">{{ $engagementScore }} / 100</span>
                                                    <span class="truncate text-slate-500 dark:text-slate-400">{{ $engagement['category'] ?? 'No score' }}</span>
                                                </div>
                                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                                    <div class="h-full rounded-full {{ $engagementToneClass }}" style="width: {{ max(0, min(100, $engagementScore)) }}%"></div>
                                                </div>
                                                <p class="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $engagement['summary'] ?? 'No engagement summary yet.' }}</p>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="min-w-[10rem]">
                                                <x-status-badge :label="$appPresence['label']" :tone="$appPresence['tone']" />
                                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $appPresence['last_seen_at']?->diffForHumans() ?? 'Never opened' }}</p>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ collect($appPresence['platforms'])->map(fn ($platform) => str($platform)->upper())->implode(', ') ?: 'Unknown platform' }}</p>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex min-w-[24rem] flex-wrap gap-1.5">
                                                <a href="{{ route('web.gym.members.show', ['member' => $member->id] + request()->only(['gym', 'branch'])) }}" class="panel-btn-primary !rounded-xl !px-3 !py-2 !text-xs">Profile</a>
                                                @if ($hasOperationalAccess)
                                                    <a href="{{ route('web.gym.members.edit', ['member' => $member->id] + request()->only(['gym', 'branch'])) }}" class="panel-btn-secondary !rounded-xl !px-3 !py-2 !text-xs">Edit / Trainer</a>
                                                    <a href="{{ route('web.gym.payments.create', ['member_id' => $member->id] + request()->query()) }}" class="panel-btn-secondary !rounded-xl !px-3 !py-2 !text-xs">Payment</a>
                                                    <a href="{{ route('web.gym.attendance.manual', ['member_id' => $member->id] + request()->query()) }}" class="panel-btn-secondary !rounded-xl !px-3 !py-2 !text-xs">Attendance</a>
                                                    <form method="POST" action="{{ route('web.gym.members.remove-from-gym', ['member' => $member->id] + request()->query()) }}" data-confirm-submit data-confirm-title="Remove member from gym?" data-confirm-message="This will cancel active gym access and make the member independent. Payment, attendance, membership, and workout history stay available for audit." data-confirm-button="Remove From Gym">
                                                        @csrf
                                                        <button type="submit" class="panel-btn-danger !rounded-xl !px-3 !py-2 !text-xs">Remove</button>
                                                    </form>
                                                @elseif ($profile?->membership_status === 'expired' && $latestMembership)
                                                    <a href="{{ route('web.gym.memberships.show', ['membership' => $latestMembership->id, 'flow' => 'lifecycle', 'action' => 'renew'] + request()->only(['gym', 'branch'])).'#renew-membership' }}" class="panel-btn-primary !rounded-xl !px-3 !py-2 !text-xs">Renew Membership</a>
                                                    <a href="{{ route('web.gym.memberships.show', ['membership' => $latestMembership->id] + request()->only(['gym', 'branch'])) }}" class="panel-btn-secondary !rounded-xl !px-3 !py-2 !text-xs">Review Cycle</a>
                                                @else
                                                    <span class="inline-flex items-center rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-300">History only</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <x-web.empty-state
                                                title="No members yet"
                                                message="Start by adding your first member manually or importing a validated CSV batch."
                                                action-label="Add Member"
                                                action-href="{{ route('web.gym.members.create', request()->query()) }}"
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                    </table>
                </div>
            </x-table-wrapper>

            <x-premium-card class="p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Showing {{ $members->firstItem() ?? 0 }} to {{ $members->lastItem() ?? 0 }} of {{ $members->total() }} members.
                    </div>
                    <div>{{ $members->links() }}</div>
                </div>
            </x-premium-card>

            @if ($importPreview)
                <x-premium-card class="p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="panel-section-title">Import Preview</h3>
                            <p class="panel-section-copy">Review ready rows, duplicates, and errors before importing.</p>
                        </div>
                        <x-status-badge :label="($importPreview['summary']['ready'] ?? 0).' ready'" tone="verified" />
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <x-stat-card label="Rows" :value="(string) $importPreview['summary']['total']" tone="sky" />
                        <x-stat-card label="Ready" :value="(string) $importPreview['summary']['ready']" tone="emerald" />
                        <x-stat-card label="Duplicates" :value="(string) $importPreview['summary']['duplicates']" tone="amber" />
                        <x-stat-card label="Errors" :value="(string) $importPreview['summary']['errors']" tone="rose" />
                    </div>

                    @if (($importPreview['summary']['ready'] ?? 0) > 0)
                        <form action="{{ route('web.gym.members.import.store', request()->query()) }}" method="POST" class="mt-5">
                            @csrf
                            <input type="hidden" name="preview_token" value="{{ $importPreview['token'] }}">
                            <x-action-button type="submit">Import Ready Rows</x-action-button>
                        </form>
                    @endif
                </x-premium-card>
            @endif
        </div>
    </div>
@endsection
