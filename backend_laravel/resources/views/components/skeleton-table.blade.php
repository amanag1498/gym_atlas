@props([
    'rows' => 5,
    'columns' => 4,
])

<x-premium-card class="p-6">
    <div class="space-y-4">
        @for ($row = 0; $row < $rows; $row++)
            <div class="grid gap-3" style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr));">
                @for ($column = 0; $column < $columns; $column++)
                    <x-skeleton-block :height="$row === 0 ? '1rem' : '0.875rem'" rounded="0.75rem" />
                @endfor
            </div>
        @endfor
    </div>
</x-premium-card>
