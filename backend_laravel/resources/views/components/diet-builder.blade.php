@props(['meals' => []])
@php
    $builderId = 'diet-builder-'.\Illuminate\Support\Str::random(8);
    $mealRows = collect($meals)->values()->all();
    if ($mealRows === []) {
        $mealRows = [
            ['name' => 'Breakfast', 'meal_type' => 'breakfast', 'items' => []],
            ['name' => 'Lunch', 'meal_type' => 'lunch', 'items' => []],
            ['name' => 'Dinner', 'meal_type' => 'dinner', 'items' => []],
        ];
    }
@endphp

<div id="{{ $builderId }}" class="space-y-4" data-diet-builder>
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="panel-label">Meals and food products</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Add meals, then add as many food/product lines as needed.</p>
        </div>
        <button type="button" class="panel-btn-secondary" data-add-meal>Add meal</button>
    </div>

    <div class="space-y-4" data-meals>
        @foreach ($mealRows as $mealIndex => $meal)
            <div class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800" data-meal>
                @if (isset($meal['id']))<input type="hidden" name="meals[{{ $mealIndex }}][id]" value="{{ $meal['id'] }}">@endif
                <div class="flex items-center justify-between gap-3">
                    <p class="font-medium text-slate-950 dark:text-white" data-meal-title>Meal {{ $mealIndex + 1 }}</p>
                    <button type="button" class="text-sm font-medium text-rose-600" data-remove-meal>Remove meal</button>
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                    <input name="meals[{{ $mealIndex }}][name]" class="panel-input" placeholder="Meal name" value="{{ $meal['name'] ?? '' }}" required>
                    <input name="meals[{{ $mealIndex }}][meal_type]" class="panel-input" placeholder="Type" value="{{ $meal['meal_type'] ?? '' }}">
                    <input name="meals[{{ $mealIndex }}][scheduled_time]" type="time" class="panel-input" value="{{ isset($meal['scheduled_time']) ? substr((string) $meal['scheduled_time'], 0, 5) : '' }}">
                </div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    @foreach (['calories' => 'Meal kcal', 'protein_g' => 'Protein g', 'carbs_g' => 'Carbs g', 'fats_g' => 'Fats g'] as $field => $placeholder)
                        <input name="meals[{{ $mealIndex }}][{{ $field }}]" type="number" min="0" step="{{ $field === 'calories' ? '1' : '0.1' }}" class="panel-input" placeholder="{{ $placeholder }}" value="{{ $meal[$field] ?? '' }}">
                    @endforeach
                </div>
                <textarea name="meals[{{ $mealIndex }}][notes]" class="panel-textarea" placeholder="Meal instructions or substitutions">{{ $meal['notes'] ?? '' }}</textarea>
                <div class="space-y-3" data-items>
                    @foreach (collect($meal['items'] ?? [])->values() as $itemIndex => $item)
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/70" data-item>
                            @if (isset($item['id']))<input type="hidden" name="meals[{{ $mealIndex }}][items][{{ $itemIndex }}][id]" value="{{ $item['id'] }}">@endif
                            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <input name="meals[{{ $mealIndex }}][items][{{ $itemIndex }}][name]" class="panel-input" placeholder="Food/product" value="{{ $item['name'] ?? '' }}">
                                <input name="meals[{{ $mealIndex }}][items][{{ $itemIndex }}][quantity]" class="panel-input" placeholder="Quantity" value="{{ $item['quantity'] ?? '' }}">
                                <input name="meals[{{ $mealIndex }}][items][{{ $itemIndex }}][calories]" type="number" min="0" class="panel-input" placeholder="kcal" value="{{ $item['calories'] ?? '' }}">
                                <input name="meals[{{ $mealIndex }}][items][{{ $itemIndex }}][protein_g]" type="number" min="0" step="0.1" class="panel-input" placeholder="Protein g" value="{{ $item['protein_g'] ?? '' }}">
                                <input name="meals[{{ $mealIndex }}][items][{{ $itemIndex }}][carbs_g]" type="number" min="0" step="0.1" class="panel-input" placeholder="Carbs g" value="{{ $item['carbs_g'] ?? '' }}">
                                <input name="meals[{{ $mealIndex }}][items][{{ $itemIndex }}][fats_g]" type="number" min="0" step="0.1" class="panel-input" placeholder="Fats g" value="{{ $item['fats_g'] ?? '' }}">
                                <input name="meals[{{ $mealIndex }}][items][{{ $itemIndex }}][notes]" class="panel-input" placeholder="Product notes" value="{{ $item['notes'] ?? '' }}">
                                <button type="button" class="panel-btn-secondary" data-remove-item>Remove product</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="panel-btn-secondary" data-add-item>Add food/product</button>
            </div>
        @endforeach
    </div>

    <template data-meal-template>
        <div class="space-y-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800" data-meal>
            <div class="flex items-center justify-between gap-3"><p class="font-medium text-slate-950 dark:text-white" data-meal-title>Meal</p><button type="button" class="text-sm font-medium text-rose-600" data-remove-meal>Remove meal</button></div>
            <div class="grid gap-3 md:grid-cols-3"><input name="meals[0][name]" class="panel-input" placeholder="Meal name" required><input name="meals[0][meal_type]" class="panel-input" placeholder="Type"><input name="meals[0][scheduled_time]" type="time" class="panel-input"></div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4"><input name="meals[0][calories]" type="number" min="0" class="panel-input" placeholder="Meal kcal"><input name="meals[0][protein_g]" type="number" min="0" step="0.1" class="panel-input" placeholder="Protein g"><input name="meals[0][carbs_g]" type="number" min="0" step="0.1" class="panel-input" placeholder="Carbs g"><input name="meals[0][fats_g]" type="number" min="0" step="0.1" class="panel-input" placeholder="Fats g"></div>
            <textarea name="meals[0][notes]" class="panel-textarea" placeholder="Meal instructions or substitutions"></textarea><div class="space-y-3" data-items></div><button type="button" class="panel-btn-secondary" data-add-item>Add food/product</button>
        </div>
    </template>
    <template data-item-template>
        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-900/70" data-item><div class="grid grid-cols-2 gap-3 lg:grid-cols-4"><input name="meals[0][items][0][name]" class="panel-input" placeholder="Food/product"><input name="meals[0][items][0][quantity]" class="panel-input" placeholder="Quantity"><input name="meals[0][items][0][calories]" type="number" min="0" class="panel-input" placeholder="kcal"><input name="meals[0][items][0][protein_g]" type="number" min="0" step="0.1" class="panel-input" placeholder="Protein g"><input name="meals[0][items][0][carbs_g]" type="number" min="0" step="0.1" class="panel-input" placeholder="Carbs g"><input name="meals[0][items][0][fats_g]" type="number" min="0" step="0.1" class="panel-input" placeholder="Fats g"><input name="meals[0][items][0][notes]" class="panel-input" placeholder="Product notes"><button type="button" class="panel-btn-secondary" data-remove-item>Remove product</button></div></div>
    </template>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById(@json($builderId));
    if (!root) return;
    const meals = root.querySelector('[data-meals]');
    const reindex = () => {
        [...meals.querySelectorAll(':scope > [data-meal]')].forEach((meal, mealIndex) => {
            meal.querySelector('[data-meal-title]').textContent = `Meal ${mealIndex + 1}`;
            [...meal.querySelectorAll('[data-item]')].forEach((item, itemIndex) => {
                item.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/meals\[\d+\]/, `meals[${mealIndex}]`).replace(/items\[\d+\]/, `items[${itemIndex}]`));
            });
            meal.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/meals\[\d+\]/, `meals[${mealIndex}]`));
        });
    };
    root.addEventListener('click', event => {
        if (event.target.closest('[data-add-meal]')) meals.append(root.querySelector('[data-meal-template]').content.cloneNode(true));
        const addItem = event.target.closest('[data-add-item]');
        if (addItem) addItem.closest('[data-meal]').querySelector('[data-items]').append(root.querySelector('[data-item-template]').content.cloneNode(true));
        const removeItem = event.target.closest('[data-remove-item]');
        if (removeItem) removeItem.closest('[data-item]').remove();
        const removeMeal = event.target.closest('[data-remove-meal]');
        if (removeMeal && meals.querySelectorAll(':scope > [data-meal]').length > 1) removeMeal.closest('[data-meal]').remove();
        reindex();
    });
    reindex();
});
</script>
@endpush
