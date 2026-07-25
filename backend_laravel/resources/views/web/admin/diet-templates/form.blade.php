@extends('layouts.panel')

@section('content')
    @php($editing = $template->exists)
    <div class="max-w-5xl">
        <x-premium-card class="p-6">
            <h2 class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $editing ? 'Edit' : 'Create' }} Global Diet Template</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Build reusable meals with complete food/product nutrition. Assignments receive a scoped copy.</p>
            <form method="POST" action="{{ $editing ? route('web.admin.diet-templates.update', $template) : route('web.admin.diet-templates.store') }}" class="mt-6 space-y-5">
                @csrf
                @if ($editing) @method('PUT') @endif
                <div class="grid gap-3 md:grid-cols-2">
                    <x-form-input name="name" label="Name" required :value="old('name', $template->name)" />
                    <x-form-input name="goal" label="Goal" :value="old('goal', $template->goal)" />
                    <x-form-input name="daily_calorie_target" type="number" label="Calories" :value="old('daily_calorie_target', $template->daily_calorie_target)" />
                    <x-form-input name="protein_target_g" type="number" step="0.1" label="Protein g" :value="old('protein_target_g', $template->protein_target_g)" />
                    <x-form-input name="carbs_target_g" type="number" step="0.1" label="Carbs g" :value="old('carbs_target_g', $template->carbs_target_g)" />
                    <x-form-input name="fats_target_g" type="number" step="0.1" label="Fats g" :value="old('fats_target_g', $template->fats_target_g)" />
                </div>
                <select name="status" class="panel-select"><option value="active" @selected(old('status', $template->status ?: 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $template->status) === 'inactive')>Inactive</option></select>
                <textarea name="dietary_preferences" class="panel-textarea" placeholder="Dietary preferences">{{ old('dietary_preferences', $template->dietary_preferences) }}</textarea>
                <textarea name="allergies_and_restrictions" class="panel-textarea" placeholder="Allergies and restrictions">{{ old('allergies_and_restrictions', $template->allergies_and_restrictions) }}</textarea>
                <textarea name="notes" class="panel-textarea" placeholder="Notes">{{ old('notes', $template->notes) }}</textarea>
                <x-diet-builder :meals="old('meals', $template->meals ?? [])" />
                <div class="flex gap-3">
                    <x-action-button type="submit">{{ $editing ? 'Save Template' : 'Create Template' }}</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.diet-templates.index') }}">Cancel</x-action-button>
                </div>
            </form>
        </x-premium-card>
    </div>
@endsection
