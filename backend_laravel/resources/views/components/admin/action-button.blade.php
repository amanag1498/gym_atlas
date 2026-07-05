@props([
    'variant' => 'primary',
    'type' => 'button',
    'as' => 'button',
])

<x-action-button :variant="$variant" :type="$type" :as="$as" {{ $attributes }}>
    {{ $slot }}
</x-action-button>
