@props([
    'title',
    'subtitle' => null,
    'series' => collect(),
    'colors' => ['#465fff', '#12b76a', '#f79009', '#f04438'],
])

@php
    $rows = collect($series)->values();
    $maximum = max(1, (float) ($rows->max('value') ?? 0));
    $total = (float) $rows->sum('value');
@endphp

<x-premium-card class="p-5">
    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current distribution</p>
    <h3 class="mt-1 text-lg font-semibold tracking-tight text-slate-950 dark:text-white">{{ $title }}</h3>
    @if ($subtitle)
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
    @endif

    <div class="mt-5 space-y-4" role="img" aria-label="{{ $title }} distribution">
        @forelse ($rows as $index => $row)
            @php
                $value = (float) $row['value'];
                $width = $maximum > 0 ? max($value > 0 ? 4 : 0, round(($value / $maximum) * 100, 2)) : 0;
                $percent = $total > 0 ? round(($value / $total) * 100) : 0;
                $color = $colors[$index % count($colors)];
            @endphp
            <div>
                <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                    <span class="truncate font-medium text-slate-700 dark:text-slate-200">{{ $row['label'] }}</span>
                    <span class="shrink-0 font-semibold text-slate-950 dark:text-white">{{ number_format($value) }} <span class="font-normal text-slate-500">({{ $percent }}%)</span></span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full transition-all" style="width: {{ $width }}%; background-color: {{ $color }}"></div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No distribution data yet.</div>
        @endforelse
    </div>
</x-premium-card>
