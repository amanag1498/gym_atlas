@props([
    'title',
    'subtitle' => null,
    'series' => collect(),
    'unit' => '',
    'accent' => '#465fff',
    'valuePrefix' => '',
])

@php
    $rows = collect($series)->values();
    $values = $rows->pluck('value')->map(fn ($value) => (float) $value);
    $maximum = max(1, (float) ($values->max() ?? 0));
    $hasData = $values->contains(fn ($value) => $value > 0);
    $width = 720;
    $height = 220;
    $top = 12;
    $bottom = 198;
    $plotHeight = $bottom - $top;
    $pointCount = max(1, $rows->count() - 1);
    $points = $rows->map(function ($row, $index) use ($width, $top, $plotHeight, $bottom, $maximum, $pointCount) {
        $x = round(($index / $pointCount) * $width, 2);
        $y = round($bottom - (((float) $row['value'] / $maximum) * $plotHeight), 2);
        return ['x' => $x, 'y' => $y, ...$row];
    });
    $polyline = $points->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
    $area = $points->isEmpty() ? '' : '0,'.$bottom.' '.$polyline.' '.$width.','.$bottom;
    $latest = $rows->last();
    $total = $values->sum();
@endphp

<x-premium-card class="overflow-hidden p-0">
    <div class="flex items-start justify-between gap-4 px-5 pt-5">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">30 day trend</p>
            <h3 class="mt-1 text-lg font-semibold tracking-tight text-slate-950 dark:text-white">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="text-right">
            <div class="text-lg font-semibold text-slate-950 dark:text-white">{{ $valuePrefix }}{{ number_format((float) $total, $valuePrefix ? 2 : 0) }}{{ $unit }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">Period total</div>
        </div>
    </div>

    <div class="px-5 pb-5 pt-4">
        @if ($hasData)
            <div class="relative h-56 w-full" role="img" aria-label="{{ $title }} for the last 30 days">
                <svg class="h-full w-full overflow-visible" viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" aria-hidden="true">
                    @foreach ([12, 74, 136, 198] as $gridY)
                        <line x1="0" y1="{{ $gridY }}" x2="{{ $width }}" y2="{{ $gridY }}" stroke="currentColor" class="text-slate-200 dark:text-slate-800" stroke-width="1" stroke-dasharray="4 6" />
                    @endforeach
                    <defs>
                        <linearGradient id="chart-fill-{{ md5($title) }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="{{ $accent }}" stop-opacity="0.28" />
                            <stop offset="100%" stop-color="{{ $accent }}" stop-opacity="0.02" />
                        </linearGradient>
                    </defs>
                    <polygon points="{{ $area }}" fill="url(#chart-fill-{{ md5($title) }})" />
                    <polyline points="{{ $polyline }}" fill="none" stroke="{{ $accent }}" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                    @foreach ($points as $point)
                        @if ((float) $point['value'] > 0)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="{{ $accent }}">
                                <title>{{ $point['label'] }}: {{ $valuePrefix }}{{ number_format((float) $point['value'], $valuePrefix ? 2 : 0) }}{{ $unit }}</title>
                            </circle>
                        @endif
                    @endforeach
                </svg>
                <ul class="sr-only">
                    @foreach ($rows as $row)
                        <li>{{ $row['label'] }}: {{ $valuePrefix }}{{ $row['value'] }}{{ $unit }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="mt-2 flex justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>{{ $rows->first()['label'] ?? '' }}</span>
                <span>Peak {{ $valuePrefix }}{{ number_format((float) ($values->max() ?? 0), $valuePrefix ? 2 : 0) }}{{ $unit }}</span>
                <span>{{ $latest['label'] ?? '' }}</span>
            </div>
        @else
            <div class="flex h-56 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-center dark:border-slate-700 dark:bg-slate-900/60">
                <i class="ti ti-chart-line text-3xl text-slate-400"></i>
                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">No activity in this period</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">The chart will populate automatically as records are created.</p>
            </div>
        @endif
    </div>
</x-premium-card>
