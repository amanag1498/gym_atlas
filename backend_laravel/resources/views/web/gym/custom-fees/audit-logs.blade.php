@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Trust and audit</p>
                    <h3 class="mt-3 text-3xl font-semibold tracking-tight text-white">Custom Fee Audit Logs</h3>
                    <p class="mt-3 max-w-2xl text-sm text-slate-300">Review every member-specific pricing change, who applied it, and the reason captured at the time of change.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.custom-fees.index', request()->query()) }}">Back to Custom Fees</x-action-button>
                </div>
            </div>
        </section>

        <div class="panel-card p-6">
            <h3 class="panel-section-title">Filter audit history</h3>
            <form method="GET" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <input type="hidden" name="gym" value="{{ request('gym') }}">
                @if (request('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif
                <input name="member_search" value="{{ request('member_search') }}" class="panel-input" placeholder="Search member">
                <select name="changed_by" class="panel-select">
                    <option value="">All actors</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((int) request('changed_by') === (int) $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
                <div class="xl:col-span-2 flex flex-wrap gap-3">
                    <button class="panel-btn-primary">Apply Filters</button>
                    <a href="{{ route('web.gym.custom-fees.audit-logs', request()->only(['gym', 'branch'])) }}" class="panel-btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            @forelse ($logs as $log)
                @php
                    $membership = $log->membership;
                @endphp
                <div class="panel-card p-6">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold text-white">{{ $log->member?->name ?? 'Member' }}</h3>
                                <x-status-badge :label="$membership?->membershipPlan?->name ?? 'Membership'" tone="info" />
                            </div>
                            <p class="mt-2 text-sm text-slate-400">
                                {{ $membership?->branch?->name ?? 'Branch not available' }}
                                <span class="mx-2 text-slate-600">•</span>
                                Changed by {{ $log->changer?->name ?? 'System' }}
                            </p>
                            <p class="mt-2 text-sm text-slate-300">{{ $log->reason ?: 'No reason recorded.' }}</p>
                        </div>
                        <x-status-badge :label="optional($log->changed_at)->format('d M Y H:i') ?: 'Unknown time'" tone="info" />
                    </div>

                    <div class="mt-5 grid gap-4 xl:grid-cols-2">
                        <div class="panel-card-muted p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Old values</p>
                            <pre class="mt-3 overflow-x-auto whitespace-pre-wrap text-xs text-slate-200">{{ json_encode($log->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                        <div class="panel-card-muted p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">New values</p>
                            <pre class="mt-3 overflow-x-auto whitespace-pre-wrap text-xs text-slate-200">{{ json_encode($log->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                </div>
            @empty
                <x-web.empty-state
                    title="No custom fee audit logs"
                    message="Fee-change history will appear here once a custom fee update is made."
                />
            @endforelse
        </div>

        @if ($logs->hasPages())
            <div class="panel-card p-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection
