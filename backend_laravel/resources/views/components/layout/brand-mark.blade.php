@props([
    'compact' => false,
    'title' => config('app.name'),
    'subtitle' => 'Admin Panel',
])

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <div class="{{ $compact ? 'h-10 w-10 rounded-2xl' : 'h-14 w-14 rounded-3xl' }} flex shrink-0 items-center justify-center bg-linear-to-br from-brand-500 via-brand-600 to-gray-950 text-white shadow-lg shadow-brand-500/20">
        <span class="{{ $compact ? 'text-base' : 'text-xl' }} font-bold tracking-[0.2em]">
            {{ str($title)->substr(0, 2)->upper() }}
        </span>
    </div>
    <div class="{{ $compact ? '' : 'sidebar-label' }}">
        <div class="text-[11px] font-semibold uppercase tracking-[0.28em] text-gray-400">{{ $subtitle }}</div>
        <div class="{{ $compact ? 'text-base' : 'text-lg' }} font-semibold text-gray-900 dark:text-white">{{ $title }}</div>
    </div>
</div>
