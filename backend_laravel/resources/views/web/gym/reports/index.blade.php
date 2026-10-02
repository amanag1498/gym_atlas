@extends('layouts.panel')

@php
    $panelFullWidth = true;
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
    $currentReportRoute = $reportNavigation[$reportKey]['route'] ?? 'web.gym.reports.index';
    $currentReportParams = $baseRouteParams;
    if (! isset($reportNavigation[$reportKey])) {
        $currentReportParams['report'] = str_replace('-', '_', $reportKey);
    }
@endphp

@section('content')
    <div class="w-full min-w-0 space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-700 dark:text-indigo-300">Gym reports</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $reportTitle }}</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-400">{{ $reportDescription }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                    <x-action-button
                        as="a"
                        variant="secondary"
                        href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'payments'])) }}"
                    >
                        Export Payments
                    </x-action-button>
                    <x-action-button
                        as="a"
                        href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => $currentExportType])) }}"
                    >
                        Export Current View
                    </x-action-button>
            </div>
        </header>

        <nav aria-label="Report types" class="flex gap-2 overflow-x-auto pb-1">
            @foreach ($reportNavigation as $key => $item)
                <a
                    href="{{ route($item['route'], array_merge($baseRouteParams, $filterQuery)) }}"
                    @if ($reportKey === $key) aria-current="page" @endif
                    class="shrink-0 rounded-xl border px-4 py-2.5 text-sm font-semibold transition {{ $reportKey === $key ? 'border-indigo-600 bg-indigo-600 text-white dark:border-indigo-400 dark:bg-indigo-400 dark:text-slate-950' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-indigo-400 dark:hover:text-white' }}"
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <x-premium-card class="min-w-0 p-5 sm:p-6">
            <div class="mb-5">
                <h3 class="panel-section-title">Refine this report</h3>
                <p class="panel-section-copy">Filters also apply to exported files.</p>
            </div>
            <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                @foreach ($currentReportParams as $field => $value)
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
                <div class="flex flex-wrap items-end gap-3 sm:col-span-2 lg:col-span-3 2xl:col-span-6">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route($currentReportRoute, $currentReportParams) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <section aria-label="Report summary" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($summaryCards as $card)
                <x-stat-card :label="$card['label']" :value="$card['value']" :hint="$card['hint'] ?? null" tone="sky" />
            @endforeach
        </section>

        @if (! empty($chartCards))
            <section aria-label="Additional metrics" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($chartCards as $card)
                    <x-stat-card :label="$card['label']" :value="$card['value']" :hint="$card['hint'] ?? null" tone="slate" />
                @endforeach
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="report-downloads-heading">
            <h3 id="report-downloads-heading" class="text-base font-semibold text-slate-950 dark:text-white">More exports</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Download related lists using the selected filters.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'members'])) }}">Export Inactive Members CSV</x-action-button>
                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'dues'])) }}">Dues CSV</x-action-button>
                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'expired-members'])) }}">Expired members CSV</x-action-button>
                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.reports.export', array_merge($baseRouteParams, $filterQuery, ['type' => 'trial-requests'])) }}">Trial requests CSV</x-action-button>
            </div>
        </section>

        <x-table-wrapper>
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="panel-section-title">{{ $reportTitle }} data</h3>
                <p class="panel-section-copy">{{ count($rows) }} {{ count($rows) === 1 ? 'record' : 'records' }} for the selected filters</p>
            </div>

            <div class="overflow-x-auto">
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
