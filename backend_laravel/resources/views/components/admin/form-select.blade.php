@props([
    'label' => null,
    'name',
    'options' => [],
    'selected' => null,
])

<x-form-select :label="$label" :name="$name" :options="$options" :selected="$selected" {{ $attributes }}>
    {{ $slot }}
</x-form-select>
