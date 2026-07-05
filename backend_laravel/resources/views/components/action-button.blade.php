@props([
    'variant' => 'primary',
    'type' => 'button',
    'as' => 'button',
])

@php
    $classes = [
        'primary' => 'panel-btn-primary gap-2',
        'secondary' => 'panel-btn-secondary gap-2',
        'danger' => 'panel-btn-danger gap-2',
    ];
@endphp

@if ($as === 'a')
    <a {{ $attributes->merge(['class' => $classes[$variant] ?? $classes['primary']]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes[$variant] ?? $classes['primary']]) }}>
        {{ $slot }}
    </button>
@endif
