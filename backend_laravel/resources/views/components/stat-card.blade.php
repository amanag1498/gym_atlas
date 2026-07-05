@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'sky',
])

@php
    $cardToneMap = [
        'sky' => 'border-sky-200/80 bg-sky-50/80 dark:border-sky-500/20 dark:bg-sky-500/10',
        'info' => 'border-sky-200/80 bg-sky-50/80 dark:border-sky-500/20 dark:bg-sky-500/10',
        'emerald' => 'border-emerald-200/80 bg-emerald-50/80 dark:border-emerald-500/20 dark:bg-emerald-500/10',
        'amber' => 'border-amber-200/80 bg-amber-50/80 dark:border-amber-500/20 dark:bg-amber-500/10',
        'warning' => 'border-amber-200/80 bg-amber-50/80 dark:border-amber-500/20 dark:bg-amber-500/10',
        'rose' => 'border-rose-200/80 bg-rose-50/80 dark:border-rose-500/20 dark:bg-rose-500/10',
        'danger' => 'border-rose-200/80 bg-rose-50/80 dark:border-rose-500/20 dark:bg-rose-500/10',
        'violet' => 'border-violet-200/80 bg-violet-50/80 dark:border-violet-500/20 dark:bg-violet-500/10',
        'neutral' => 'border-slate-200/80 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-800/60',
    ];

    $iconToneMap = [
        'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        'info' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        'danger' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
        'neutral' => 'bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-300',
    ];
@endphp

<div class="panel-card h-full border {{ $cardToneMap[$tone] ?? $cardToneMap['sky'] }}">
    <div class="p-3.5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <small class="text-[10px] font-medium uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">{{ $label }}</small>
                <div class="mt-2 text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">{{ $value }}</div>
                @if ($hint)
                    <div class="mt-1.5 text-[11px] leading-5 text-slate-500 dark:text-slate-400">{{ $hint }}</div>
                @endif
            </div>
            <div class="inline-flex h-9 w-9 items-center justify-center rounded-xl {{ $iconToneMap[$tone] ?? $iconToneMap['sky'] }}">
                <i class="ti ti-chart-bar text-sm"></i>
            </div>
        </div>
    </div>
</div>
