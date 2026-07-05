@props(['class' => ''])

<div {{ $attributes->merge(['class' => trim("panel-card overflow-hidden {$class}")]) }}>
    {{ $slot }}
</div>
