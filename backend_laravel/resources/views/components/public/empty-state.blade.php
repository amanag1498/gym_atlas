@props([
    'title',
    'message',
    'actionLabel' => null,
    'actionHref' => null,
])

<x-public.card class="public-card-static text-center rounded-[1.6rem]">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-[1.1rem] bg-[linear-gradient(135deg,rgba(59,130,246,0.16),rgba(56,189,248,0.16))] text-2xl text-sky-300 shadow-lg shadow-sky-500/10">
        ✦
    </div>
    <h3 class="mt-5 text-xl font-semibold tracking-tight text-white">{{ $title }}</h3>
    <p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-slate-400">{{ $message }}</p>
    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="public-btn public-btn-primary mt-6 inline-flex rounded-full px-5 py-3 text-sm font-semibold">
            {{ $actionLabel }}
        </a>
    @endif
</x-public.card>
