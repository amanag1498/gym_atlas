@props([
    'eyebrow' => 'Ready to move faster?',
    'title',
    'copy',
    'primaryLabel',
    'primaryHref',
    'secondaryLabel' => null,
    'secondaryHref' => null,
])

<section class="public-story-panel relative overflow-hidden rounded-[1.8rem] px-6 py-10 sm:px-8 lg:px-12">
    <div class="absolute inset-y-0 right-0 hidden w-80 bg-[radial-gradient(circle_at_center,rgba(70,199,255,0.24),transparent_68%)] lg:block"></div>
    <div class="public-orbit -right-24 top-8 hidden h-64 w-64 lg:block"></div>
    <div class="relative max-w-3xl">
        <p class="public-eyebrow">{{ $eyebrow }}</p>
        <h2 class="mt-5 text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $title }}</h2>
        <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300">{{ $copy }}</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ $primaryHref }}" class="public-btn public-btn-primary inline-flex rounded-full px-5 py-3 text-sm font-semibold">
                {{ $primaryLabel }}
            </a>
            @if ($secondaryLabel && $secondaryHref)
                <a href="{{ $secondaryHref }}" class="public-btn public-btn-secondary inline-flex rounded-full px-5 py-3 text-sm font-semibold">
                    {{ $secondaryLabel }}
                </a>
            @endif
        </div>
    </div>
</section>
