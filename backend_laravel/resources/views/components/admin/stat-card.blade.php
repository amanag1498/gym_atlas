@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'sky',
])

<x-stat-card :label="$label" :value="$value" :hint="$hint" :tone="$tone" />
