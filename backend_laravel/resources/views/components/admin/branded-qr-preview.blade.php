@props([
    'src',
    'alt' => 'GymAtlas QR code',
    'eyebrow' => 'SCAN TO CONTINUE',
    'caption' => 'Open your camera and point it at the code',
    'tone' => 'event',
    'size' => 'md',
])

@php
    $gradient = $tone === 'enrollment'
        ? 'from-emerald-950 via-teal-800 to-slate-950'
        : 'from-indigo-950 via-brand-600 to-slate-950';
    $imageSize = match ($size) {
        'sm' => 'h-40 w-40',
        'lg' => 'h-48 w-48',
        default => 'h-44 w-44',
    };
@endphp

<div {{ $attributes->class(["relative overflow-hidden rounded-[1.6rem] bg-gradient-to-br {$gradient} p-3 shadow-xl shadow-slate-950/15"]) }}>
    <div class="pointer-events-none absolute -right-10 -top-12 h-36 w-36 rounded-full border border-white/15"></div>
    <div class="pointer-events-none absolute -right-2 -top-5 h-20 w-20 rounded-full border border-white/10"></div>
    <div class="relative rounded-[1.15rem] bg-white p-3 shadow-inner shadow-slate-200">
        <div class="mb-2 flex items-center justify-between gap-2 px-1">
            <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-slate-500">{{ $eyebrow }}</span>
            <span class="inline-flex items-center gap-1 text-[9px] font-bold text-brand-600"><i class="ti ti-sparkles"></i> GymAtlas</span>
        </div>
        <img src="{{ $src }}" alt="{{ $alt }}" class="{{ $imageSize }} mx-auto" loading="lazy">
        <p class="mt-2 text-center text-[10px] font-medium leading-4 text-slate-500">{{ $caption }}</p>
    </div>
</div>
