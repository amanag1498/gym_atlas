@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'panel-card overflow-hidden']) }}>
    <div class="flex flex-col gap-4 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            @if ($eyebrow)
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                    {{ $eyebrow }}
                </span>
            @endif

            <div class="mt-2 flex min-w-0 flex-wrap items-center gap-2">
                <h2 class="text-xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $title }}</h2>
            </div>

            @if ($description)
                <p class="mt-1.5 max-w-3xl text-[13px] leading-5 text-slate-500 dark:text-gray-400">
                    {{ $description }}
                </p>
            @endif
        </div>

        @if (trim($slot))
            <div class="flex flex-wrap items-center gap-2">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
