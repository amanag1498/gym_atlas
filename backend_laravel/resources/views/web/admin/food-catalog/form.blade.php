@extends('layouts.panel')

@section('content')
    @php($editing = $food->exists)
    <div class="max-w-5xl">
        <x-premium-card class="p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:text-sky-300">Platform food catalog</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $editing ? 'Edit food' : 'Add food' }}</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Nutrition values describe the default serving. Diet creators may customize the copied serving without changing this catalog entry.</p>

            <form method="POST" action="{{ $editing ? route('web.admin.food-catalog.update', $food) : route('web.admin.food-catalog.store') }}" class="mt-6 space-y-5">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="grid gap-3 md:grid-cols-2">
                    <x-form-input name="name" label="Food name" required :value="old('name', $food->name)" />
                    <x-form-input name="category" label="Category" :value="old('category', $food->category)" placeholder="Fruit, grain, dairy..." />
                    <x-form-input name="default_quantity" label="Default serving" :value="old('default_quantity', $food->default_quantity)" placeholder="1 medium, 100 g, 1 cup..." />
                    <x-form-input name="serving_size_g" type="number" min="0" step="0.1" label="Serving weight (g)" :value="old('serving_size_g', $food->serving_size_g)" />
                    <x-form-input name="calories" type="number" min="0" label="Calories" :value="old('calories', $food->calories)" />
                    <x-form-input name="protein_g" type="number" min="0" step="0.1" label="Protein (g)" :value="old('protein_g', $food->protein_g)" />
                    <x-form-input name="carbs_g" type="number" min="0" step="0.1" label="Carbs (g)" :value="old('carbs_g', $food->carbs_g)" />
                    <x-form-input name="fats_g" type="number" min="0" step="0.1" label="Fats (g)" :value="old('fats_g', $food->fats_g)" />
                    <x-form-input name="fiber_g" type="number" min="0" step="0.1" label="Fiber (g)" :value="old('fiber_g', $food->fiber_g)" />
                    <x-form-input name="image_url" type="url" label="Image URL" :value="old('image_url', $food->image_url)" />
                    <x-form-input name="dietary_tags" label="Dietary tags" :value="old('dietary_tags', collect($food->dietary_tags ?? [])->join(', '))" placeholder="vegetarian, vegan, gluten-free" />
                    <x-form-input name="allergens" label="Allergens" :value="old('allergens', collect($food->allergens ?? [])->join(', '))" placeholder="milk, nuts, soy" />
                </div>
                <textarea name="notes" class="panel-textarea" placeholder="Preparation or catalog notes">{{ old('notes', $food->notes) }}</textarea>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $food->is_active ?? true))>
                    <span><strong class="block text-sm text-slate-950 dark:text-white">Active in diet builders</strong><span class="text-xs text-slate-500">Turn this off to prevent new selections without changing existing diets.</span></span>
                </label>
                <div class="flex gap-3">
                    <x-action-button type="submit">{{ $editing ? 'Save Food' : 'Add Food' }}</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.food-catalog.index') }}">Cancel</x-action-button>
                </div>
            </form>
        </x-premium-card>
    </div>
@endsection
