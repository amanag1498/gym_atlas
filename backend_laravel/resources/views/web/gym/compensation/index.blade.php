@extends('layouts.panel')

@section('content')
    @php
        $scopeQuery = array_filter(['gym' => $gym->id, 'branch' => request('branch'), 'month' => $month->format('Y-m')]);
        $editingProfile = $editingProfile ?? null;
    @endphp

    <div class="space-y-5">
        <x-premium-card class="overflow-hidden p-0">
            <div class="bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 px-5 py-6 text-white sm:px-7">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-indigo-200">Team payroll</p>
                        <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Salary & Commission</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">See what each trainer or staff member earned, what has been paid, and which membership collection created each commission.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.payments.index', request()->only(['gym', 'branch'])) }}">Finance Ledger</x-action-button>
                        <form method="GET" action="{{ route('web.gym.compensation.index') }}" class="flex gap-2">
                            <input type="hidden" name="gym" value="{{ $gym->id }}">
                            @if(request('branch'))<input type="hidden" name="branch" value="{{ request('branch') }}">@endif
                            <label class="sr-only" for="compensation-month">Statement month</label>
                            <input id="compensation-month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="panel-input !w-auto border-white/15 !bg-white/10 !text-white" required>
                            <x-action-button type="submit">View</x-action-button>
                        </form>
                    </div>
                </div>
            </div>
            <nav class="flex gap-1 overflow-x-auto border-t border-slate-800 bg-slate-950 px-4 py-2" aria-label="Salary and commission sections">
                <a href="#overview" class="whitespace-nowrap rounded-xl px-3 py-2 text-sm font-medium text-white hover:bg-white/10">Overview</a>
                <a href="#team-setup" class="whitespace-nowrap rounded-xl px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white">Team setup</a>
                <a href="#payouts" class="whitespace-nowrap rounded-xl px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white">Payouts</a>
                <a href="#commission-audit" class="whitespace-nowrap rounded-xl px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white">Commission audit</a>
            </nav>
        </x-premium-card>

        <section id="overview" class="scroll-mt-24 space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Remaining to pay" :value="'₹'.number_format($overview['remaining'], 2)" :hint="$overview['attention_count'].' statements need action'" tone="amber" />
                <x-stat-card label="Paid this month" :value="'₹'.number_format($overview['paid'], 2)" hint="Recorded payroll payouts" tone="emerald" />
                <x-stat-card label="Commission earned" :value="'₹'.number_format($overview['earned_commission'], 2)" hint="From collected membership extras" tone="violet" />
                <x-stat-card label="Fixed salary" :value="'₹'.number_format($overview['salary_commitment'], 2)" :hint="$overview['active_profiles'].' active profiles'" tone="sky" />
            </div>

            @if($overview['unprocessed_commission'] > 0)
                <div class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-950 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-100 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="font-semibold">₹{{ number_format($overview['unprocessed_commission'], 2) }} commission is ready to add to statements.</p><p class="mt-1 text-sm opacity-80">Generate or refresh {{ $month->format('F') }} statements to include the latest collections.</p></div>
                    @if($canManage)<form method="POST" action="{{ route('web.gym.compensation.generate', ['gym' => $gym->id]) }}">@csrf<input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><x-action-button type="submit">Refresh Statements</x-action-button></form>@endif
                </div>
            @endif

            <div class="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                <x-premium-card class="p-5">
                    <div class="flex items-start justify-between gap-4"><div><h2 class="panel-section-title">Owner checklist</h2><p class="panel-section-copy">The shortest path from collection to a reconciled payout.</p></div><x-status-badge :label="$month->format('M Y')" tone="info" /></div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <a href="#team-setup" class="panel-card-muted p-4 transition hover:border-indigo-300 dark:hover:border-indigo-500/40"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">1 · Team setup</p><p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $overview['active_profiles'] }} active salary profiles</p><p class="mt-1 text-sm text-slate-500">Add fixed salary only where the gym has committed one.</p></a>
                        <a href="{{ route('web.gym.memberships.index', request()->only(['gym', 'branch'])) }}" class="panel-card-muted p-4 transition hover:border-indigo-300 dark:hover:border-indigo-500/40"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">2 · Commission rules</p><p class="mt-2 font-semibold text-slate-950 dark:text-white">Attach rules to memberships</p><p class="mt-1 text-sm text-slate-500">Commission is calculated only from collected PT extra.</p></a>
                        <a href="#payouts" class="panel-card-muted p-4 transition hover:border-indigo-300 dark:hover:border-indigo-500/40"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">3 · Review</p><p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $overview['attention_count'] }} statements awaiting payment</p><p class="mt-1 text-sm text-slate-500">Open a statement to inspect its salary and commission sources.</p></a>
                        <a href="{{ route('web.gym.payments.index', request()->only(['gym', 'branch'])) }}" class="panel-card-muted p-4 transition hover:border-indigo-300 dark:hover:border-indigo-500/40"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">4 · Reconcile</p><p class="mt-2 font-semibold text-slate-950 dark:text-white">Payroll entries in the ledger</p><p class="mt-1 text-sm text-slate-500">Every payout creates a linked finance outflow.</p></a>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-5">
                    <h2 class="panel-section-title">Generate monthly statements</h2><p class="panel-section-copy">Build draft payouts from effective salaries and commission earned in this month.</p>
                    @if($canManage)
                        <form method="POST" action="{{ route('web.gym.compensation.generate', ['gym' => $gym->id]) }}" class="mt-5 space-y-3">@csrf<label class="panel-label" for="generate-month">Statement month</label><input id="generate-month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="panel-input" required><x-action-button type="submit" class="w-full justify-center">Generate / Refresh Statements</x-action-button></form>
                    @else
                        <x-empty-state title="View-only access" message="A user with payment management permission must generate or pay statements." />
                    @endif
                    <p class="mt-4 text-xs leading-5 text-slate-500">Draft statements can be refreshed. Partially paid and paid statements stay unchanged to protect the payout trail.</p>
                </x-premium-card>
            </div>
        </section>

        <section id="team-setup" class="scroll-mt-24 grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
            @if($canManage)
                <x-premium-card class="p-5">
                    <div class="flex items-start justify-between gap-3"><div><h2 class="panel-section-title">{{ $editingProfile ? 'Edit salary profile' : 'Add salary profile' }}</h2><p class="panel-section-copy">Only active trainers and staff attached to this gym are available.</p></div>@if($editingProfile)<a href="{{ route('web.gym.compensation.index', $scopeQuery) }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">Cancel</a>@endif</div>
                    @if($teamMembers->isEmpty())
                        <x-empty-state title="No eligible team members" message="Add or activate a trainer or staff member before creating salary profiles." />
                        <div class="mt-4 flex gap-2"><x-action-button as="a" variant="secondary" href="{{ route('web.gym.trainers.index', request()->only(['gym', 'branch'])) }}">Open Trainers</x-action-button><x-action-button as="a" variant="secondary" href="{{ route('web.gym.staff.index', request()->only(['gym', 'branch'])) }}">Open Staff</x-action-button></div>
                    @else
                        <form method="POST" action="{{ route('web.gym.compensation.profiles.store', ['gym' => $gym->id]) }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
                            <div class="md:col-span-2"><label class="panel-label">Trainer or staff member</label><select name="user_id" class="panel-select" required><option value="">Select team member</option>@foreach($teamMembers as $person)<option value="{{ $person->id }}" @selected((int)old('user_id', $editingProfile?->user_id) === $person->id)>{{ $person->name }} · {{ ucfirst($person->getAttribute('compensation_role')) }}</option>@endforeach</select></div>
                            <div><label class="panel-label">Payroll category</label><div class="panel-input flex items-center text-slate-500">Detected from the selected team member</div></div>
                            <div><label class="panel-label">Branch scope</label><select name="branch_id" class="panel-select"><option value="">Gym-wide</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)old('branch_id', $editingProfile?->branch_id) === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                            <div><label class="panel-label">Monthly fixed salary</label><input name="monthly_salary" type="number" min="0" step="0.01" value="{{ old('monthly_salary', $editingProfile?->monthly_salary) }}" class="panel-input" required></div>
                            <div><label class="panel-label">Expected payout day</label><input name="payout_day" type="number" min="1" max="28" value="{{ old('payout_day', $editingProfile?->payout_day ?? 1) }}" class="panel-input" required></div>
                            <div><label class="panel-label">Effective from</label><input name="effective_from" type="date" value="{{ old('effective_from', $editingProfile?->effective_from?->toDateString()) }}" class="panel-input"></div>
                            <div><label class="panel-label">Effective until</label><input name="effective_until" type="date" value="{{ old('effective_until', $editingProfile?->effective_until?->toDateString()) }}" class="panel-input"></div>
                            <label class="md:col-span-2 flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $editingProfile?->is_active ?? true))> Active salary profile</label>
                            <x-action-button type="submit" class="md:col-span-2 justify-center">{{ $editingProfile ? 'Update Salary Profile' : 'Save Salary Profile' }}</x-action-button>
                        </form>
                    @endif
                </x-premium-card>
            @endif

            <x-premium-card class="p-5">
                <div class="flex items-start justify-between"><div><h2 class="panel-section-title">Salary profiles</h2><p class="panel-section-copy">The fixed monthly commitment before commissions.</p></div><x-status-badge :label="$profiles->count().' profiles'" tone="neutral" /></div>
                <div class="mt-5 space-y-3">
                    @forelse($profiles as $profile)
                        <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 px-4 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><p class="font-semibold text-slate-950 dark:text-white">{{ $profile->user?->name ?? 'Unavailable user' }}</p><x-status-badge :label="$profile->is_active ? 'Active' : 'Inactive'" :tone="$profile->is_active ? 'success' : 'neutral'" /></div><p class="mt-1 text-sm text-slate-500">{{ ucfirst($profile->worker_type) }} · {{ $profile->branch?->name ?? 'Gym-wide' }} · payout day {{ $profile->payout_day }}</p></div><div class="flex items-center gap-3"><p class="text-lg font-semibold text-slate-950 dark:text-white">₹{{ number_format((float)$profile->monthly_salary, 2) }}</p>@if($canManage)<a href="{{ route('web.gym.compensation.index', $scopeQuery + ['edit_profile' => $profile->id]) }}#team-setup" class="panel-btn-secondary !px-3 !py-2">Edit</a>@endif</div></div>
                    @empty<x-empty-state title="No salary profiles" message="Create a profile only for trainers or staff who receive a fixed monthly salary." />@endforelse
                </div>
            </x-premium-card>
        </section>

        <section id="payouts" class="scroll-mt-24">
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="panel-section-title">{{ $month->format('F Y') }} payout queue</h2><p class="panel-section-copy">Open a statement to inspect earnings, payout history, and ledger links.</p></div><p class="text-sm font-semibold text-slate-700 dark:text-slate-200">₹{{ number_format($overview['remaining'], 2) }} remaining</p></div>
                <div class="overflow-x-auto"><table class="panel-table min-w-[900px]"><thead><tr><th>Team member</th><th>Salary</th><th>Commission</th><th>Net payable</th><th>Paid</th><th>Status</th><th class="text-right">Action</th></tr></thead><tbody>
                    @forelse($statements as $statement)
                        @php($remaining = max(0, (float)$statement->net_payable_amount - (float)$statement->paid_amount))
                        <tr><td><div class="font-medium text-slate-950 dark:text-white">{{ $statement->user?->name ?? 'Unavailable user' }}</div><div class="text-xs text-slate-500">{{ $statement->branch?->name ?? 'Gym-wide' }}</div></td><td>₹{{ number_format((float)$statement->salary_amount, 2) }}</td><td>₹{{ number_format((float)$statement->commission_amount, 2) }}</td><td class="font-semibold">₹{{ number_format((float)$statement->net_payable_amount, 2) }}</td><td><div>₹{{ number_format((float)$statement->paid_amount, 2) }}</div>@if($remaining > 0)<div class="text-xs text-amber-600">₹{{ number_format($remaining, 2) }} left</div>@endif</td><td><x-status-badge :label="str($statement->status)->replace('_',' ')->title()" :tone="$statement->status === 'paid' ? 'success' : 'warning'" /></td><td class="text-right"><a href="{{ route('web.gym.compensation.statements.show', ['statement' => $statement->id] + request()->only(['gym', 'branch'])) }}" class="panel-btn-primary !px-3 !py-2">View Statement</a></td></tr>
                    @empty<tr><td colspan="7"><x-empty-state title="No statements generated" message="Generate this month after salary profiles or commission-bearing collections exist." /></td></tr>@endforelse
                </tbody></table></div>
            </x-table-wrapper>
        </section>

        <section id="commission-audit" class="scroll-mt-24">
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="panel-section-title">Commission audit</h2><p class="panel-section-copy">Each row connects collected PT extra to its recipient, member, rule, and payment.</p></div>
                <div class="overflow-x-auto"><table class="panel-table min-w-[950px]"><thead><tr><th>Date</th><th>Recipient</th><th>Member</th><th>Collected extra</th><th>Commission</th><th>Rule</th><th>Source</th></tr></thead><tbody>
                    @forelse($earnings as $earning)
                        <tr><td>{{ optional($earning->earned_at)->format('d M Y') }}</td><td><div class="font-medium text-slate-950 dark:text-white">{{ $earning->recipient?->name ?? 'Unavailable user' }}</div><div class="text-xs text-slate-500">{{ ucfirst($earning->allocation?->recipient_type ?? 'Team') }}</div></td><td>{{ $earning->allocation?->membership?->member?->name ?? 'Unavailable member' }}</td><td>₹{{ number_format((float)$earning->commissionable_collected_amount, 2) }}</td><td class="font-semibold">₹{{ number_format((float)$earning->amount, 2) }}</td><td>{{ ucfirst($earning->allocation?->calculation_type) }} {{ $earning->allocation?->calculation_type === 'percentage' ? number_format((float)$earning->allocation?->value, 2).'%' : '₹'.number_format((float)$earning->allocation?->value, 2) }}</td><td>@if($earning->allocation?->membership)<a href="{{ route('web.gym.memberships.show', ['membership' => $earning->allocation->membership->id] + request()->only(['gym', 'branch'])) }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">Membership</a>@else<span class="text-sm text-slate-500">Unavailable</span>@endif</td></tr>
                    @empty<tr><td colspan="7"><x-empty-state title="No commission earned" message="Commission appears after money collected on a membership exceeds its base price." /></td></tr>@endforelse
                </tbody></table></div>
            </x-table-wrapper>
        </section>
    </div>
@endsection
