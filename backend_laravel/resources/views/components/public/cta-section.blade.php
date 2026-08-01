@props([
    'eyebrow' => 'Ready to move faster?',
    'title',
    'copy',
    'primaryLabel',
    'primaryHref',
    'secondaryLabel' => null,
    'secondaryHref' => null,
])

<section class="public-cta-compact">
    <div class="public-cta-compact-copy">
        <p class="public-eyebrow">{{ $eyebrow }}</p>
        <h2>{{ $title }}</h2>
        <p>{{ $copy }}</p>
    </div>
    <div class="public-cta-compact-actions">
            <a href="{{ $primaryHref }}" class="public-btn public-btn-primary inline-flex rounded-full px-5 py-3 text-sm font-semibold">
                {{ $primaryLabel }}
            </a>
            @if ($secondaryLabel && $secondaryHref)
                <a href="{{ $secondaryHref }}" class="public-btn public-btn-secondary inline-flex rounded-full px-5 py-3 text-sm font-semibold">
                    {{ $secondaryLabel }}
                </a>
            @endif
    </div>
</section>
