@props([
    'label' => null,
    'name',
    'id' => null,
    'options' => [],
    'selected' => null,
])

@php($fieldId = $id ?? $name)

<div>
    @if ($label)
        <label for="{{ $fieldId }}" class="panel-label">{{ $label }}</label>
    @endif
    <select id="{{ $fieldId }}" name="{{ $name }}" {{ $attributes->merge(['class' => 'panel-select']) }}>
        @if (count($options))
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $text }}</option>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>
</div>
