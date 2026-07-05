@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Reporting</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $reportTitle }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $reportDescription }}</p>
                </div>
                <x-action-button as="a" href="{{ route('web.admin.reports.export', ['type' => $reportKey] + request()->query()) }}">Export CSV</x-action-button>
            </div>
        </section>

        <x-premium-card class="p-5">
            <div class="flex flex-wrap gap-2">
                @foreach ($reportNavigation as $key => $item)
                    <x-action-button
                        as="a"
                        :variant="$reportKey === $key ? 'primary' : 'secondary'"
                        href="{{ route($item['route'], request()->except('page')) }}"
                    >
                        {{ $item['label'] }}
                    </x-action-button>
                @endforeach
            </div>
        </x-premium-card>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[repeat(4,minmax(0,1fr))_minmax(180px,1fr)]">
                <x-form-input name="start_date" label="Start Date" type="date" :value="$filters['start_date']" />
                <x-form-input name="end_date" label="End Date" type="date" :value="$filters['end_date']" />
                <x-form-select name="city" label="City" :selected="$filters['city']">
                    <option value="">All cities</option>
                    @foreach ($filterOptions['cities'] as $city)
                        <option value="{{ $city }}" @selected($filters['city'] === $city)>{{ $city }}</option>
                    @endforeach
                </x-form-select>
                <x-form-select name="gym" label="Gym" :selected="$filters['gym']">
                    <option value="">All gyms</option>
                    @foreach ($filterOptions['gyms'] as $gym)
                        <option value="{{ $gym->id }}" @selected((string) $filters['gym'] === (string) $gym->id)>{{ $gym->name }}</option>
                    @endforeach
                </x-form-select>
                <x-form-select name="status" label="Status" :selected="$filters['status']" :options="$filterOptions['statuses']" />
                <div class="md:col-span-2 xl:col-span-5 flex flex-wrap gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route($reportNavigation[$reportKey]['route']) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($summaryCards as $card)
                <x-stat-card :label="$card['label']" :value="$card['value']" :hint="$card['hint'] ?? ''" tone="sky" />
            @endforeach
        </div>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">{{ $reportTitle }} Table</h3>
                    <p class="panel-section-copy">Filtered report rows with CSV export support.</p>
                </div>
                <x-status-badge :label="count($rows).' rows'" tone="neutral" />
            </div>

            @if (! empty($rows))
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[980px]">
                        <thead>
                            <tr>
                                @foreach ($columns as $column)
                                    <th>{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td>{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5">
                    <x-empty-state :title="$emptyState['title']" :message="$emptyState['message']" />
                </div>
            @endif
        </x-table-wrapper>

        <x-premium-card class="p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="panel-section-title">Trend Snapshot</h3>
                    <p class="panel-section-copy">Filtered movement from the same report dataset, ready for quick review without leaving the panel.</p>
                </div>
                <x-status-badge :label="count($chartCards).' trend points'" tone="info" />
            </div>

            @if (! empty($chartCards))
                @php
                    $trendValues = collect($chartCards)
                        ->map(fn ($card) => is_numeric($card['value'] ?? null) ? (float) $card['value'] : null)
                        ->filter(fn ($value) => $value !== null);
                    $maxTrendValue = max(1, (float) ($trendValues->max() ?? 1));
                @endphp
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($chartCards as $card)
                        @php
                            $trendValue = is_numeric($card['value'] ?? null) ? (float) $card['value'] : null;
                            $trendWidth = $trendValue === null ? 100 : max(8, (int) round(($trendValue / $maxTrendValue) * 100));
                        @endphp
                        <div class="panel-card-muted h-full p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">{{ $card['label'] }}</div>
                            <div class="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $card['value'] }}</div>
                            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800" role="img" aria-label="{{ $card['label'] }} trend value {{ $card['value'] }}">
                                <div class="h-full rounded-full bg-linear-to-r from-teal-600 to-sky-500" style="width: {{ $trendWidth }}%"></div>
                            </div>
                            <div class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $card['hint'] ?? 'Trend insight' }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-5">
                    <x-empty-state title="No trend data" message="Trend snapshots will appear here when enough filtered report data exists." />
                </div>
            @endif
        </x-premium-card>
    </div>
@endsection
