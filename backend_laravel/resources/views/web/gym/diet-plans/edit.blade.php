@extends('layouts.panel')

@section('content')
    <div class="max-w-5xl">
        <x-premium-card class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:text-sky-300">Assigned nutrition</p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Edit {{ $plan->name }}</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Member: {{ $plan->member?->name ?? 'Member' }}. Existing meal completion history is retained for meals that remain in the plan.
                    </p>
                </div>
                <x-status-badge :label="ucfirst($plan->status)" :tone="$plan->status === 'active' ? 'success' : 'warning'" />
            </div>

            <form method="POST" action="{{ route('web.gym.diet-plans.update', ['dietPlan' => $plan->id, 'gym' => $gym->id, 'branch' => $plan->branch_id]) }}" class="mt-6 space-y-5">
                @csrf
                @method('PUT')

                <div class="grid gap-3 md:grid-cols-2">
                    <x-form-input name="name" label="Plan name" required :value="old('name', $plan->name)" />
                    <x-form-input name="goal" label="Goal" :value="old('goal', $plan->goal)" />
                    <x-form-input name="daily_calorie_target" type="number" min="0" label="Daily calories" :value="old('daily_calorie_target', $plan->daily_calorie_target)" />
                    <x-form-input name="protein_target_g" type="number" min="0" step="0.1" label="Protein (g)" :value="old('protein_target_g', $plan->protein_target_g)" />
                    <x-form-input name="carbs_target_g" type="number" min="0" step="0.1" label="Carbs (g)" :value="old('carbs_target_g', $plan->carbs_target_g)" />
                    <x-form-input name="fats_target_g" type="number" min="0" step="0.1" label="Fats (g)" :value="old('fats_target_g', $plan->fats_target_g)" />
                    <x-form-input name="starts_on" type="date" label="Starts on" :value="old('starts_on', $plan->starts_on?->toDateString())" />
                    <x-form-input name="ends_on" type="date" label="Ends on" :value="old('ends_on', $plan->ends_on?->toDateString())" />
                </div>

                <x-form-select name="status" label="Status" required>
                    <option value="active" @selected(old('status', $plan->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $plan->status) === 'inactive')>Inactive</option>
                </x-form-select>
                <textarea name="dietary_preferences" class="panel-textarea" placeholder="Dietary preferences">{{ old('dietary_preferences', $plan->dietary_preferences) }}</textarea>
                <textarea name="allergies_and_restrictions" class="panel-textarea" placeholder="Allergies and restrictions">{{ old('allergies_and_restrictions', $plan->allergies_and_restrictions) }}</textarea>
                <textarea name="notes" class="panel-textarea" placeholder="Coach notes">{{ old('notes', $plan->notes) }}</textarea>

                <x-diet-builder :meals="old('meals', $plan->meals->map(fn ($meal) => $meal->toArray() + ['items' => $meal->items->toArray()])->all())" />

                <div class="flex flex-wrap gap-3">
                    <x-action-button type="submit">Save Diet Plan</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.diet-plans.index', ['gym' => $gym->id, 'branch' => $plan->branch_id]) }}">Cancel</x-action-button>
                </div>
            </form>
        </x-premium-card>
    </div>
@endsection
