@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:text-sky-300">Platform nutrition</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Food Catalog</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Global foods available to members, trainers and gyms when building diets. Existing plans always retain their saved nutrition snapshot.</p>
            </div>
            <x-action-button as="a" href="{{ route('web.admin.food-catalog.create') }}">Add Food</x-action-button>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <x-stat-card label="Catalog foods" :value="$foods->total()" hint="Matching current filters" tone="sky" />
            <x-stat-card label="Active" :value="$activeCount" hint="Available in diet builders" tone="emerald" />
            <x-stat-card label="Categories" :value="$categoryCount" hint="Platform-managed groups" tone="violet" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-3 sm:grid-cols-[1fr_180px_auto]">
                <input name="search" class="panel-input" value="{{ request('search') }}" placeholder="Search food or category">
                <select name="status" class="panel-select">
                    <option value="">All statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
                <x-action-button type="submit" variant="secondary">Filter</x-action-button>
            </form>
        </x-premium-card>

        <x-table-wrapper>
            <div class="overflow-x-auto">
                <table class="panel-table">
                    <thead><tr><th>Food</th><th>Serving</th><th>Nutrition</th><th>Tags</th><th>Usage</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($foods as $food)
                            <tr>
                                <td><p class="font-medium text-slate-950 dark:text-white">{{ $food->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $food->category ?: 'Uncategorized' }}</p></td>
                                <td>{{ $food->default_quantity ?: '—' }}@if($food->serving_size_g)<br><span class="text-xs text-slate-500">{{ $food->serving_size_g }} g</span>@endif</td>
                                <td>{{ $food->calories ?? '—' }} kcal<br><span class="text-xs text-slate-500">P {{ $food->protein_g ?? '—' }} · C {{ $food->carbs_g ?? '—' }} · F {{ $food->fats_g ?? '—' }}</span></td>
                                <td class="text-xs">{{ collect($food->dietary_tags ?? [])->join(', ') ?: '—' }}</td>
                                <td>{{ $food->diet_meal_items_count }}</td>
                                <td><x-status-badge :label="$food->is_active ? 'Active' : 'Inactive'" :tone="$food->is_active ? 'success' : 'warning'" /></td>
                                <td><x-action-button as="a" variant="secondary" href="{{ route('web.admin.food-catalog.edit', $food) }}">Edit</x-action-button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-empty-state title="No catalog foods" message="Add the first reusable food for diet creators." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $foods->links() }}</div>
        </x-table-wrapper>
    </div>
@endsection
