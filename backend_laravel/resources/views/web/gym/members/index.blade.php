@extends('layouts.panel')

@section('content')
    @php
        $memberCollection = $members->getCollection();
        $visibleMembersCount = $memberCollection->count();
        $highRiskCount = $memberCollection->filter(fn ($member) => (($member->engagement_score['category'] ?? $member->memberProfile?->engagement_score['category'] ?? null) === 'High Risk'))->count();
        $noTrainerCount = $memberCollection->filter(fn ($member) => blank($member->memberProfile?->assignedTrainer?->name))->count();
        $expiringSoonCount = $memberCollection->filter(fn ($member) => in_array(strtolower((string) ($member->memberProfile?->membership_status ?? '')), ['expiring soon', 'expiring_soon'], true))->count();
        $dueMembersCount = $memberCollection->filter(fn ($member) => (float) ($member->memberMemberships->first()?->due_amount ?? 0) > 0)->count();
        $assignedTrainerCount = max(0, $visibleMembersCount - $noTrainerCount);
    @endphp

    <div class="space-y-4">
        <section class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-linear-to-br from-slate-950 via-slate-900 to-sky-950 text-white shadow-[0_24px_80px_-36px_rgba(15,23,42,0.75)] dark:border-slate-800">
            <div class="grid gap-6 px-5 py-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)] lg:px-6">
                <div>
                    <div class="inline-flex items-center rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-100">
                        Member Operations
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Member Directory</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                        Profiles, trainer allocation, membership pressure, and day-to-day follow-up in one operator-grade workspace.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <x-action-button as="a" href="{{ route('web.gym.members.create', request()->query()) }}">Add Member</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">Export CSV</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.index', array_merge(request()->query(), ['report' => 'inactive_members'])) }}">Inactive Report</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.memberships.index', request()->query()) }}">Open Memberships</x-action-button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Visible Members</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $visibleMembersCount }}</div>
                        <div class="mt-1 text-sm text-slate-300">members on this page</div>
                    </div>
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Trainer Coverage</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $assignedTrainerCount }}</div>
                        <div class="mt-1 text-sm text-slate-300">{{ $noTrainerCount }} still unassigned</div>
                    </div>
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Payment Pressure</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $dueMembersCount }}</div>
                        <div class="mt-1 text-sm text-slate-300">members with open due</div>
                    </div>
                    <div class="rounded-[22px] border border-white/10 bg-white/8 p-4 backdrop-blur">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-100/80">Risk Watch</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">{{ $highRiskCount }}</div>
                        <div class="mt-1 text-sm text-slate-300">high-risk engagement profiles</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="Filtered Members" :value="$members->total()" hint="Current result set" tone="sky" />
            <x-stat-card label="High Risk" :value="$highRiskCount" hint="Needs intervention" tone="rose" />
            <x-stat-card label="No Trainer" :value="$noTrainerCount" hint="Coverage gap" tone="amber" />
            <x-stat-card label="Expiring Soon" :value="$expiringSoonCount" hint="Renewal pressure" tone="violet" />
            <x-stat-card label="Due Now" :value="$dueMembersCount" hint="Billing follow-up" tone="emerald" />
        </div>

        <div class="space-y-4">
            <x-premium-card class="overflow-hidden p-0">
                <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Filter and Operate</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Search by profile, trainer, branch, plan, dues, and inactivity without leaving the list.</p>
                        </div>
                        <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                            {{ $members->total() }} members in scope
                        </div>
                    </div>
                </div>

                <form method="GET" class="grid gap-3 px-5 py-4 md:grid-cols-2 xl:grid-cols-6">
                    <x-form-input name="search" label="Search" :value="request('search')" />
                    <x-form-select name="status" label="Status" :options="['' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive', 'expired' => 'Expired', 'expiring_soon' => 'Expiring Soon', 'due_payment' => 'Due Payment', 'overdue' => 'Overdue']" :selected="request('status')" />
                    <x-form-select name="trainer_id" label="Trainer" :options="['' => 'All trainers'] + $trainers->pluck('name', 'id')->all()" :selected="request('trainer_id')" />
                    <x-form-select name="branch_id" label="Branch" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" :selected="request('branch_id')" />
                    <x-form-select name="plan_id" label="Plan" :options="['' => 'All plans'] + $plans->pluck('name', 'id')->all()" :selected="request('plan_id')" />
                    <x-form-select name="gender" label="Gender" :options="['' => 'All genders', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :selected="request('gender')" />
                    <x-form-input name="goal" label="Goal" :value="request('goal')" />
                    <label class="flex items-end">
                        <span class="flex w-full items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                            <input type="checkbox" name="no_trainer_assigned" value="1" @checked(request()->boolean('no_trainer_assigned'))>
                            No Trainer Assigned
                        </span>
                    </label>
                    <label class="flex items-end">
                        <span class="flex w-full items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                            <input type="checkbox" name="inactive_7_days" value="1" @checked(request()->boolean('inactive_7_days'))>
                            Inactive 7 Days
                        </span>
                    </label>
                    <div class="flex items-end gap-2 xl:col-span-2">
                        <x-action-button type="submit">Apply Filters</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.members.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                    </div>
                </form>
            </x-premium-card>

            <div class="grid gap-4 lg:grid-cols-3">
                <x-premium-card class="p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Member intake</p>
                    <h3 class="mt-1 text-base font-semibold tracking-tight text-slate-950 dark:text-white">Create individual member</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Use guided setup for branch, trainer, plan, and fee defaults.</p>
                    <div class="mt-4">
                        <x-action-button as="a" href="{{ route('web.gym.members.create', request()->query()) }}">Open Create Flow</x-action-button>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Batch import</p>
                    <h3 class="mt-1 text-base font-semibold tracking-tight text-slate-950 dark:text-white">Preview CSV before intake</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Supported fields include name, email, phone, gender, branch, trainer, plan, and start date.</p>
                    <form action="{{ route('web.gym.members.import.preview', request()->query()) }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <x-form-input name="members_csv" label="CSV File" type="file" required />
                        <x-action-button type="submit" class="w-full">Preview Import</x-action-button>
                    </form>
                </x-premium-card>

                <x-premium-card class="p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Watchlist</p>
                    <h3 class="mt-1 text-base font-semibold tracking-tight text-slate-950 dark:text-white">Immediate member pressure</h3>
                    <div class="mt-4 space-y-3">
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Due now</span>
                            <x-status-badge :label="$dueMembersCount" tone="warning" />
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <span class="text-sm text-slate-600 dark:text-slate-300">High risk</span>
                            <x-status-badge :label="$highRiskCount" tone="danger" />
                        </div>
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Expiring soon</span>
                            <x-status-badge :label="$expiringSoonCount" tone="info" />
                        </div>
                    </div>
                </x-premium-card>
            </div>

            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Members in scope</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Compact operating table for profile review, billing follow-up, and trainer actions.</p>
                    </div>
                    <div class="hidden md:flex md:flex-wrap md:gap-2">
                        <x-status-badge :label="'Visible '.$visibleMembersCount" tone="info" />
                        <x-status-badge :label="'No trainer '.$noTrainerCount" tone="warning" />
                        <x-status-badge :label="'Due '.$dueMembersCount" tone="danger" />
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1320px]">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Membership</th>
                                    <th>Trainer / Branch</th>
                                    <th>Goal / Profile</th>
                                    <th>Engagement</th>
                                    <th class="w-[25rem]">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($members as $member)
                                    @php
                                        $profile = $member->memberProfile;
                                        $latestMembership = $member->memberMemberships->first();
                                        $engagement = $member->engagement_score ?? $profile?->engagement_score ?? null;
                                        $engagementScore = (int) ($engagement['score'] ?? 0);
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
                                                    <a href="{{ route('web.gym.members.show', $member) }}" class="block truncate font-semibold text-slate-950 hover:text-brand-600 dark:text-white dark:hover:text-brand-300">{{ $member->name }}</a>
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
                                                    <x-status-badge :label="ucfirst($profile?->membership_status ?? 'active')" />
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
                                            <div class="flex min-w-[24rem] flex-wrap gap-1.5">
                                                <a href="{{ route('web.gym.members.show', $member) }}" class="panel-btn-primary !rounded-xl !px-3 !py-2 !text-xs">Profile</a>
                                                <a href="{{ route('web.gym.members.edit', $member) }}" class="panel-btn-secondary !rounded-xl !px-3 !py-2 !text-xs">Edit / Trainer</a>
                                                <a href="{{ route('web.gym.payments.create', ['member_id' => $member->id] + request()->query()) }}" class="panel-btn-secondary !rounded-xl !px-3 !py-2 !text-xs">Payment</a>
                                                <a href="{{ route('web.gym.attendance.manual', ['member_id' => $member->id] + request()->query()) }}" class="panel-btn-secondary !rounded-xl !px-3 !py-2 !text-xs">Attendance</a>
                                                <form method="POST" action="{{ route('web.gym.members.remove-from-gym', ['member' => $member->id] + request()->query()) }}" data-confirm-submit data-confirm-title="Remove member from gym?" data-confirm-message="This will cancel active gym access and make the member independent. Payment, attendance, membership, and workout history stay available for audit." data-confirm-button="Remove From Gym">
                                                    @csrf
                                                    <button type="submit" class="panel-btn-danger !rounded-xl !px-3 !py-2 !text-xs">Remove</button>
                                                </form>
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
