@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.trainer-specializations.create') }}">Add Specialization</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Trainer Identity</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Trainer Specialization Master</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Manage the trainer-facing specialization catalog used in onboarding and profile setup with the same compact premium admin system.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Active</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $activeCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">live trainer setup options</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Inactive</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $inactiveCount }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">retained for safe rollback</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Specializations" :value="$trainerSpecializations->total()" hint="Platform catalog size" tone="sky" />
            <x-stat-card label="Active" :value="$activeCount" hint="Visible in trainer setup" tone="emerald" />
            <x-stat-card label="Inactive" :value="$inactiveCount" hint="Hidden but retained" tone="amber" />
            <x-stat-card label="Loaded" :value="$trainerSpecializations->count()" hint="Current page results" tone="violet" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1.8fr)_minmax(220px,1fr)_auto]">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Search specializations by name or description" />
                <x-form-select name="status" label="Status" :selected="request('status')" :options="['' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive']" />
                <div class="flex items-end gap-2">
                    <x-action-button type="submit">Apply Filters</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.trainer-specializations.index') }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between dark:border-slate-800">
                <div>
                    <h3 class="panel-section-title">Specialization Directory</h3>
                    <p class="panel-section-copy">Active options appear in trainer onboarding and profile management flows across the platform.</p>
                </div>
                <x-status-badge :label="$trainerSpecializations->total().' total'" tone="neutral" />
            </div>

            @if ($trainerSpecializations->count() > 0)
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[1220px]">
                        <thead>
                            <tr>
                                <th>Specialization</th>
                                <th>Presentation</th>
                                <th>Status</th>
                                <th>Usage</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trainerSpecializations as $specialization)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $specialization->name }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $specialization->slug }}</div>
                                        <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $specialization->description ?: 'No description added yet.' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>Icon {{ $specialization->icon ?: 'Not set' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sort priority {{ $specialization->sort_order }}</div>
                                    </td>
                                    <td>
                                        <x-status-badge :label="$specialization->is_active ? 'Active' : 'Inactive'" :tone="$specialization->is_active ? 'success' : 'danger'" />
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        <div>{{ $specialization->trainer_profiles_count ?? 0 }} trainers</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $specialization->is_active ? 'Available in trainer onboarding' : 'Removed from new selections' }}</div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-action-button as="a" href="{{ route('web.admin.trainer-specializations.edit', $specialization) }}" variant="secondary">Edit</x-action-button>
                                            <form action="{{ route('web.admin.trainer-specializations.toggle-status', $specialization) }}" method="POST" data-confirm-submit data-confirm-title="{{ $specialization->is_active ? 'Deactivate specialization?' : 'Activate specialization?' }}" data-confirm-message="{{ $specialization->is_active ? 'This specialization will stop appearing in trainer onboarding.' : 'This specialization will become available in trainer onboarding again.' }}" data-confirm-button="{{ $specialization->is_active ? 'Deactivate' : 'Activate' }}">
                                                @csrf
                                                <x-action-button type="submit" variant="{{ $specialization->is_active ? 'danger' : 'primary' }}">{{ $specialization->is_active ? 'Deactivate' : 'Activate' }}</x-action-button>
                                            </form>
                                            @if (($specialization->trainer_profiles_count ?? 0) > 0)
                                                <x-action-button as="span" variant="secondary">In Use</x-action-button>
                                            @else
                                                <form action="{{ route('web.admin.trainer-specializations.destroy', $specialization) }}" method="POST" data-confirm-submit data-confirm-title="Delete specialization?" data-confirm-message="This will permanently delete the trainer specialization." data-confirm-button="Delete">
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
                    <x-empty-state title="No trainer specializations found" message="Create the first specialization so trainers can complete onboarding with platform-managed options." action-label="Add Specialization" :action-href="route('web.admin.trainer-specializations.create')" />
                </div>
            @endif

            @if ($trainerSpecializations->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    {{ $trainerSpecializations->links() }}
                </div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
