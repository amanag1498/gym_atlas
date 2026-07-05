@props([
    'label' => null,
    'name',
    'options' => [],
    'selected' => null,
])

<div>
    @if ($label)
        <label for="{{ $name }}" class="panel-label">{{ $label }}</label>
    @endif
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->merge(['class' => 'panel-select']) }}>
        @if (count($options))
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $text }}</option>
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>
</div>
