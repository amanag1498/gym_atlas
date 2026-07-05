@extends('layouts.panel')

@section('content')
    <div class="space-y-5">
        <div class="grid gap-3 lg:grid-cols-4">
            <x-stat-card label="Trainers" :value="$trainers->total()" hint="Visible in current gym scope" tone="sky" />
            <x-stat-card label="Active" :value="$trainers->getCollection()->filter(fn ($trainer) => (bool) $trainer->managedTrainerProfile?->is_active)->count()" hint="Active trainers on this page" tone="emerald" />
            <x-stat-card label="Assigned Members" :value="$trainers->getCollection()->sum(fn ($trainer) => (int) ($trainer->assigned_members_count ?? 0))" hint="Current page workload" tone="amber" />
            <x-stat-card label="Specializations" :value="$trainers->getCollection()->flatMap(fn ($trainer) => $trainer->managedTrainerProfile?->specializations ?? [])->filter()->unique()->count()" hint="Distinct skills in scope" tone="violet" />
        </div>

        <x-premium-card class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="panel-section-title">Trainer Directory</h3>
                    <p class="panel-section-copy">Compact operational view of roster, branch placement, workload, and coaching readiness.</p>
                </div>
                <a href="{{ route('web.gym.trainers.create', request()->query()) }}" class="panel-btn-primary">Add Trainer</a>
            </div>

            <form method="GET" class="mt-5 grid gap-4 md:grid-cols-5">
                <x-form-input name="search" label="Search" :value="request('search')" placeholder="Name, email, phone" />
                <div>
                    <label for="branch_id" class="panel-label">Branch</label>
                    <select id="branch_id" name="branch_id" class="panel-select">
                        <option value="">All branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) request('branch_id') === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="panel-label">Status</label>
                    <select id="status" name="status" class="panel-select">
                        <option value="">All status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <x-form-input name="specialization" label="Specialization" :value="request('specialization')" placeholder="Strength, rehab, yoga..." />
                <div class="flex items-end gap-3">
                    <x-action-button type="submit" variant="secondary">Filter</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-premium-card class="overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50/90 dark:bg-slate-900/80">
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-4 py-3">Trainer</th>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3">Specialization</th>
                            <th class="px-4 py-3">Signals</th>
                            <th class="px-4 py-3">Profile</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($trainers as $trainer)
                            @php($profile = $trainer->managedTrainerProfile)
                            @php($assignedCount = (int) ($trainer->assigned_members_count ?? 0))
                            @php($specializationLabel = $profile?->specialization ?? collect($profile?->specializations)->filter()->join(', '))
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-white/10 dark:bg-slate-900/70">
                                            @if (filled($profile?->profile_photo_url) || filled($trainer->avatar))
                                                <img src="{{ $profile?->profile_photo_url ?: $trainer->avatar }}" alt="{{ $trainer->name }}" class="h-full w-full object-cover">
                                            @else
                                                <span class="text-sm font-semibold text-slate-950 dark:text-white">{{ strtoupper(substr($trainer->name, 0, 1)) }}</span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-950 dark:text-white">{{ $trainer->name }}</p>
                                            <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $trainer->email }}</p>
                                            @if ($hasPhoneColumn && filled($trainer->phone))
                                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $trainer->phone }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-2">
                                        <x-status-badge :label="$profile?->branch?->name ?? 'Gym-wide'" tone="info" />
                                        <x-status-badge :label="$profile?->verification_status ?: 'Unverified'" :tone="filled($profile?->verification_status) && str($profile->verification_status)->lower()->value() === 'verified' ? 'verified' : 'neutral'" />
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $specializationLabel ?: 'Not set' }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ (int) ($profile?->experience_years ?? 0) }} yrs experience</p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-1 text-sm">
                                        <p class="text-slate-950 dark:text-white">{{ $assignedCount }} assigned members</p>
                                        <p class="text-slate-500 dark:text-slate-400">{{ collect($profile?->languages)->filter()->take(3)->join(', ') ?: 'Languages not set' }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-2">
                                        <x-status-badge :label="$profile?->status ?? ($trainer->is_active ? 'Active' : 'Inactive')" />
                                        @if (filled($profile?->availability_notes))
                                            <p class="max-w-xs text-xs leading-5 text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($profile->availability_notes, 90) }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('web.gym.trainers.show', ['trainer' => $trainer->id] + request()->query()) }}" class="panel-btn-secondary !px-3 !py-2">View</a>
                                        <a href="{{ route('web.gym.trainers.edit', ['trainer' => $trainer->id] + request()->query()) }}" class="panel-btn-secondary !px-3 !py-2">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10">
                                    <x-empty-state title="No trainers yet" message="Create the first trainer and start assigning members by branch and specialization." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-premium-card>

        <div>{{ $trainers->links() }}</div>
    </div>
@endsection
