@props([
    'compact' => false,
    'title' => config('app.name'),
    'subtitle' => 'Admin Panel',
])

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    @if ($compact)
        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[#07152f] p-1.5 shadow-lg shadow-brand-500/20">
            <img src="{{ asset('images/brand/gym-atlas-mark.png') }}" alt="{{ $title }}" class="h-full w-full object-contain">
        </span>
    @else
        <span class="sidebar-label min-w-0">
            <img src="{{ asset('images/brand/gym-atlas-lockup.png') }}" alt="Gym Atlas" class="h-auto w-[210px] object-contain dark:hidden">
            <img src="{{ asset('images/brand/generated/gym-atlas-lockup-on-dark.png') }}" alt="Gym Atlas" class="hidden h-auto w-[210px] object-contain dark:block">
            <span class="mt-1 block text-[10px] font-semibold uppercase tracking-[0.24em] text-gray-400">{{ $subtitle }}</span>
        </span>
    @endif
</div>
