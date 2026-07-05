@props(['class' => ''])

<x-table-wrapper :class="$class">
    {{ $slot }}
</x-table-wrapper>
