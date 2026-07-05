@props([
    'label' => null,
    'name',
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
])

<x-form-input :label="$label" :name="$name" :type="$type" :value="$value" :placeholder="$placeholder" {{ $attributes }} />
