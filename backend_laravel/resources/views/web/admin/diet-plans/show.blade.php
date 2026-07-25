@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:text-sky-300">Platform diet oversight</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950 dark:text-white">{{ $plan->name }}</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ $plan->gym?->name ?? 'Unknown gym' }} · {{ $plan->branch?->name ?? 'Gym-wide' }} · {{ $plan->member?->name ?? 'Unknown member' }}
                </p>
            </div>
            <div class="flex gap-2">
                <x-status-badge :label="ucfirst($plan->status)" :tone="$plan->status === 'active' ? 'success' : 'warning'" />
                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.diet-plans.index') }}">Back</x-action-button>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <x-stat-card label="Calories" :value="$plan->daily_calorie_target ?? '—'" hint="Daily kcal target" tone="sky" />
            <x-stat-card label="Protein" :value="$plan->protein_target_g ?? '—'" hint="Grams per day" tone="emerald" />
            <x-stat-card label="Carbs" :value="$plan->carbs_target_g ?? '—'" hint="Grams per day" tone="violet" />
            <x-stat-card label="Fats" :value="$plan->fats_target_g ?? '—'" hint="Grams per day" tone="amber" />
        </div>

        <x-premium-card class="p-6">
            <div class="grid gap-5 md:grid-cols-2">
                <div><p class="panel-label">Goal</p><p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $plan->goal ?: 'Not specified' }}</p></div>
                <div><p class="panel-label">Schedule</p><p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $plan->starts_on?->format('d M Y') ?? 'Immediate' }} – {{ $plan->ends_on?->format('d M Y') ?? 'Ongoing' }}</p></div>
                <div><p class="panel-label">Dietary preferences</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $plan->dietary_preferences ?: 'None recorded' }}</p></div>
                <div><p class="panel-label">Allergies and restrictions</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $plan->allergies_and_restrictions ?: 'None recorded' }}</p></div>
                <div class="md:col-span-2"><p class="panel-label">Notes</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $plan->notes ?: 'No notes' }}</p></div>
            </div>
        </x-premium-card>

        <div class="space-y-4">
            @foreach ($plan->meals as $meal)
                <x-premium-card class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-950 dark:text-white">{{ $meal->name }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $meal->meal_type ?: 'Meal' }} · {{ $meal->scheduled_time ? substr((string) $meal->scheduled_time, 0, 5) : 'No fixed time' }}</p>
                        </div>
                        <p class="text-sm text-slate-500">{{ $meal->calories ?? '—' }} kcal · P {{ $meal->protein_g ?? '—' }} · C {{ $meal->carbs_g ?? '—' }} · F {{ $meal->fats_g ?? '—' }}</p>
                    </div>
                    @if ($meal->notes)<p class="mt-3 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $meal->notes }}</p>@endif
                    <div class="mt-4 overflow-x-auto">
                        <table class="panel-table">
                            <thead><tr><th>Food / product</th><th>Quantity</th><th>Calories</th><th>Macros</th><th>Notes</th></tr></thead>
                            <tbody>
                                @forelse ($meal->items as $item)
                                    <tr>
                                        <td class="font-medium text-slate-950 dark:text-white">{{ $item->name }}</td>
                                        <td>{{ $item->quantity ?: '—' }}</td>
                                        <td>{{ $item->calories ?? '—' }}</td>
                                        <td class="text-xs text-slate-500">P {{ $item->protein_g ?? '—' }} · C {{ $item->carbs_g ?? '—' }} · F {{ $item->fats_g ?? '—' }}</td>
                                        <td>{{ $item->notes ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-sm text-slate-500">No individual product lines.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-premium-card>
            @endforeach
        </div>
    </div>
@endsection
