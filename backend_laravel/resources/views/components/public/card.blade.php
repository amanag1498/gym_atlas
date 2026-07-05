@props(['class' => ''])

<div {{ $attributes->merge(['class' => trim('public-card rounded-[1.45rem] p-6 '.$class)]) }}>
    {{ $slot }}
</div>
