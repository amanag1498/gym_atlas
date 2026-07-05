@extends('layouts.panel')

@php
    $currentGym = request('gym', $gym->id);
    $currentBranch = request('branch');
    $baseRouteParams = array_filter([
        'gym' => $currentGym,
        'branch' => $currentBranch,
    ], fn ($value) => filled($value));
    $filterQuery = array_filter([
        'start_date' => $filters['start_date'],
        'end_date' => $filters['end_date'],
        'branch_id' => $filters['branch_id'],
        'trainer_id' => $filters['trainer_id'],
        'plan_id' => $filters['plan_id'],
        'status' => $filters['status'],
    ], fn ($value) => filled($value));
@endphp

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Gym reports</p>
                    <h3 class="mt-3 text-3xl font-semibold tracking-tight text-white">{{ $reportTitle }}</h3>
                    <p class="mt-3 max-w-2xl text-sm text-slate-300">{{ $reportDescription }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-action-button
                        as="a"
                        variant="secondary"
                        href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'payments'])) }}"
                    >
                        Export Payments
                    </x-action-button>
                    <x-action-button
                        as="a"
                        href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => match($reportKey) {
                            'dues' => 'dues',
                            'attendance' => 'attendance',
                            'memberships' => 'expiring-members',
                            'custom-fees' => 'custom-fee-report',
                            'leads' => 'trial-requests',
                            default => 'payments',
                        }])) }}"
                    >
                        Export Current View
                    </x-action-button>
                </div>
            </div>
        </section>

        <div class="grid gap-3 xl:grid-cols-8">
            @foreach ($reportNavigation as $key => $item)
                <a
                    href="{{ route($item['route'], array_merge($baseRouteParams, $filterQuery)) }}"
                    class="rounded-2xl border px-4 py-3 text-sm font-semibold transition {{ $reportKey === $key ? 'border-sky-400/60 bg-sky-500/15 text-white shadow-lg shadow-sky-950/40' : 'border-white/10 bg-white/5 text-slate-300 hover:border-white/20 hover:bg-white/10 hover:text-white' }}"
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        <x-premium-card class="p-6">
            <form method="GET" class="grid gap-4 lg:grid-cols-6">
                @foreach ($baseRouteParams as $field => $value)
                    <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                @endforeach

                <x-form-input name="start_date" label="Start Date" type="date" :value="$filters['start_date']" />
                <x-form-input name="end_date" label="End Date" type="date" :value="$filters['end_date']" />
                <x-form-select
                    name="branch_id"
                    label="Branch"
                    :options="['' => 'All Branches'] + $filterOptions['branches']->pluck('name', 'id')->all()"
                    :selected="$filters['branch_id']"
                />
                <x-form-select
                    name="trainer_id"
                    label="Trainer"
                    :options="['' => 'All Trainers'] + $filterOptions['trainers']->mapWithKeys(fn ($trainer) => [$trainer->user_id => $trainer->user?->name ?? 'Trainer'])->all()"
                    :selected="$filters['trainer_id']"
                />
                <x-form-select
                    name="plan_id"
                    label="Plan"
                    :options="['' => 'All Plans'] + $filterOptions['plans']->pluck('name', 'id')->all()"
                    :selected="$filters['plan_id']"
                />
                <x-form-select
                    name="status"
                    label="Status"
                    :options="$filterOptions['statuses']"
                    :selected="$filters['status']"
                />
                <div class="flex items-end gap-3 lg:col-span-6">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route($reportNavigation[$reportKey]['route'], $baseRouteParams) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($summaryCards as $card)
                <x-stat-card :label="$card['label']" :value="$card['value']" tone="sky" />
            @endforeach
        </section>

        @if (! empty($chartCards))
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($chartCards as $card)
                    <x-stat-card :label="$card['label']" :value="$card['value']" tone="slate" />
                @endforeach
            </section>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'members'])) }}">Export Members CSV</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'dues'])) }}">Export Dues CSV</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'expired-members'])) }}">Export Expired Members CSV</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'trial-requests'])) }}">Export Trial Requests CSV</x-action-button>
        </section>

        <x-table-wrapper>
            <h3 class="panel-section-title">{{ $reportTitle }}</h3>
            <p class="panel-section-copy">{{ $reportDescription }}</p>

            <div class="mt-6 overflow-x-auto">
                <table class="panel-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) }}">
                                    <x-empty-state :title="$emptyState['title']" :message="$emptyState['message']" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-table-wrapper>
    </div>
@endsection
