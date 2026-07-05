@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.fitness-goals.create') }}">Add Goal</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Member Identity</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Fitness Goal Master</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Manage the member-facing goal catalog used in onboarding, profile setup, and downstream coaching context without carrying heavy visual chrome.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Active Goals</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $activeCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">currently available in member flows</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Inactive Goals</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $inactiveCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">preserved for audit and rollback</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Goals" :value="$fitnessGoals->total()" hint="Platform catalog size" tone="sky" />
            <x-stat-card label="Active" :value="$activeCount" hint="Visible in setup" tone="emerald" />
            <x-stat-card label="Inactive" :value="$inactiveCount" hint="Hidden but retained" tone="amber" />
            <x-stat-card label="Loaded" :value="$fitnessGoals->count()" hint="Current page results" tone="violet" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1.8fr)_minmax(220px,1fr)_auto]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Search goals by name or description" />
                <x-form-select name="status" label="Status" :selected="request('status')" :options="['' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive']" />
                <div class="flex items-end gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.fitness-goals.index') }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Goal Directory</h3>
                    <p class="panel-section-copy">Keep the onboarding goal library searchable, ordered, and clean enough for member profile flows.</p>
                </div>
                <x-status-badge :label="$fitnessGoals->total().' total'" tone="neutral" />
            </div>

            @if ($fitnessGoals->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1220px]">
                        <thead>
                            <tr>
                                <th>Goal</th>
                                <th>Presentation</th>
                                <th>Status</th>
                                <th>Usage</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fitnessGoals as $fitnessGoal)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $fitnessGoal->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $fitnessGoal->slug }}</div>
                                        <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $fitnessGoal->description ?: 'No description added yet.' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>Icon {{ $fitnessGoal->icon ?: 'Not set' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sort priority {{ $fitnessGoal->sort_order }}</div>
                                    </td>
                                    <td>
                                        <x-status-badge :label="$fitnessGoal->is_active ? 'Active' : 'Inactive'" :tone="$fitnessGoal->is_active ? 'success' : 'danger'" />
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ $fitnessGoal->member_profiles_count ?? 0 }} member profiles</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $fitnessGoal->is_active ? 'Available in onboarding' : 'Removed from new selections' }}</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" href="{{ route('web.admin.fitness-goals.edit', $fitnessGoal) }}" variant="secondary">Edit</x-action-button>
                                            <form action="{{ route('web.admin.fitness-goals.toggle-status', $fitnessGoal) }}" method="POST" data-confirm-submit data-confirm-title="{{ $fitnessGoal->is_active ? 'Deactivate fitness goal?' : 'Activate fitness goal?' }}" data-confirm-message="{{ $fitnessGoal->is_active ? 'This goal will stop appearing in the member onboarding flow.' : 'This goal will become available in the member onboarding flow again.' }}" data-confirm-button="{{ $fitnessGoal->is_active ? 'Deactivate' : 'Activate' }}">
                                                @csrf
                                                <x-action-button type="submit" variant="{{ $fitnessGoal->is_active ? 'danger' : 'primary' }}">{{ $fitnessGoal->is_active ? 'Deactivate' : 'Activate' }}</x-action-button>
                                            </form>
                                            @if (($fitnessGoal->member_profiles_count ?? 0) > 0)
                                                <x-action-button as="span" variant="secondary">In Use</x-action-button>
                                            @else
                                                <form action="{{ route('web.admin.fitness-goals.destroy', $fitnessGoal) }}" method="POST" data-confirm-submit data-confirm-title="Delete fitness goal?" data-confirm-message="This will permanently delete the goal from the platform catalog." data-confirm-button="Delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-action-button type="submit" variant="danger">Delete</x-action-button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5">
                    <x-empty-state title="No fitness goals found" message="Create the first goal so members can personalize onboarding and coaching preferences." action-label="Add Goal" :action-href="route('web.admin.fitness-goals.create')" />
                </div>
            @endif

            @if ($fitnessGoals->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    {{ $fitnessGoals->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
