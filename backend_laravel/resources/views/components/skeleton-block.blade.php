@props([
    'height' => '1rem',
    'width' => '100%',
    'rounded' => '999px',
    'class' => '',
])

<div
    {{ $attributes->merge([
        'class' => 'animate-pulse border border-white/6 bg-white/[0.06] shadow-[0_10px_30px_rgba(0,0,0,0.18)] ' . $class,
        'style' => "height: {$height}; width: {$width}; border-radius: {$rounded};",
    ]) }}
></div>
