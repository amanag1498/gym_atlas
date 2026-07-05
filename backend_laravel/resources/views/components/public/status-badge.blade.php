@props([
    'label',
    'tone' => 'neutral',
])

@php
    $tones = [
        'success' => 'border-emerald-400/16 bg-emerald-400/10 text-emerald-200',
        'warning' => 'border-amber-400/16 bg-amber-400/10 text-amber-200',
        'danger' => 'border-rose-400/16 bg-rose-400/10 text-rose-200',
        'info' => 'border-sky-400/16 bg-sky-400/10 text-sky-200',
        'featured' => 'border-blue-400/16 bg-blue-400/10 text-blue-200',
        'promoted' => 'border-indigo-400/16 bg-indigo-400/10 text-indigo-200',
        'neutral' => 'border-slate-600/70 bg-slate-900/70 text-slate-300',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] '.($tones[$tone] ?? $tones['neutral'])]) }}>
    {{ $label }}
</span>
