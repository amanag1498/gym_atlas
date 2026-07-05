@props([
    'label' => null,
    'name',
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
])

<div>
    @if ($label)
        <label for="{{ $name }}" class="panel-label">{{ $label }}</label>
    @endif
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'panel-input']) }}
    >
</div>
