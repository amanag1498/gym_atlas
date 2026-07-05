@props([
    'title',
    'message',
    'actionLabel' => null,
    'actionHref' => null,
])

<div class="flex flex-col items-center justify-center gap-3 rounded-[1.25rem] border border-dashed border-gray-300 bg-gray-50 px-5 py-8 text-center dark:border-gray-700 dark:bg-gray-900/60">
    <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
        <i class="ti ti-layout-grid-add text-xl"></i>
    </div>
    <div>
        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</p>
        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $message }}</p>
    </div>
    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="panel-btn-primary mt-1">{{ $actionLabel }}</a>
    @endif
</div>
