@extends('layouts.panel')

@section('content')
    <div class="space-y-5">
        <x-premium-card class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div class="flex items-center gap-4">
                    <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-3xl border border-slate-200 bg-slate-100 dark:border-white/10 dark:bg-slate-900/70">
                        @if (filled($trainerProfile?->profile_photo_url) || filled($trainer->avatar))
                            <img src="{{ $trainerProfile?->profile_photo_url ?: $trainer->avatar }}" alt="{{ $trainer->name }}" class="h-full w-full object-cover">
                        @else
                            <span class="text-2xl font-semibold text-slate-950 dark:text-white">{{ strtoupper(substr($trainer->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div>
                        <h2 class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $trainer->name }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $trainer->email }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-status-badge :label="$trainerProfile?->status ?? ($trainer->is_active ? 'Active' : 'Inactive')" />
                            <x-status-badge :label="$trainerProfile?->branch?->name ?? 'Gym-wide'" tone="info" />
                            <x-status-badge :label="$trainerProfile?->verification_status ?: 'Unverified'" :tone="filled($trainerProfile?->verification_status) && str($trainerProfile->verification_status)->lower()->value() === 'verified' ? 'verified' : 'neutral'" />
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if ($canManageTrainer)
                        <a href="{{ route('web.gym.trainers.edit', ['trainer' => $trainer->id] + request()->query()) }}" class="panel-btn-secondary">Edit Trainer</a>
                        <form action="{{ route('web.gym.trainers.' . ($trainer->is_active ? 'deactivate' : 'activate'), ['trainer' => $trainer->id] + request()->query()) }}" method="POST">
                            @csrf
                            <x-action-button type="submit" :variant="$trainer->is_active ? 'danger' : 'primary'">
                                {{ $trainer->is_active ? 'Deactivate' : 'Activate' }}
                            </x-action-button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="mt-6 grid gap-3 lg:grid-cols-5">
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Specialization</p>
                    <p class="mt-2 text-sm font-medium text-slate-950 dark:text-white">{{ $trainerProfile?->specialization ?? collect($trainerProfile?->specializations)->filter()->join(', ') ?: 'Not set' }}</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Experience</p>
                    <p class="mt-2 text-sm font-medium text-slate-950 dark:text-white">{{ (int) ($trainerProfile?->experience_years ?? 0) }} years</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Assigned Members</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ $assignedMembers->count() }}</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Workload Summary</p>
                    <p class="mt-2 text-sm font-medium text-slate-950 dark:text-white">{{ $assignedMembers->count() >= 10 ? 'High load' : ($assignedMembers->count() >= 5 ? 'Healthy load' : 'Capacity available') }}</p>
                </div>
                <div class="panel-card-muted px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-slate-500">Languages</p>
                    <p class="mt-2 text-sm font-medium text-slate-950 dark:text-white">{{ collect($trainerProfile?->languages)->filter()->join(', ') ?: 'Not set' }}</p>
                </div>
            </div>
        </x-premium-card>

        <div class="grid gap-5 xl:grid-cols-[1.08fr_0.92fr]">
            <div class="space-y-5">
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Profile Intelligence</h3>
                    <div class="mt-4 grid gap-3">
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Certifications</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse (collect($trainerProfile?->certifications)->filter() as $certification)
                                    <x-status-badge :label="$certification" tone="info" />
                                @empty
                                    <span class="text-sm text-slate-500 dark:text-slate-400">No certifications listed.</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Availability</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $trainerProfile?->availability_notes ?: 'Availability notes not added yet.' }}</p>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Bio</p>
                            <p class="mt-2 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $trainerProfile?->bio ?: 'No trainer bio written yet.' }}</p>
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Assigned Members</h3>
                    <p class="panel-section-copy">Members currently routed to this trainer inside the allowed gym and branch scope.</p>

                    <div class="mt-5 space-y-3">
                        @forelse ($assignedMembers as $member)
                            <div class="panel-card-muted flex items-center justify-between gap-4 px-4 py-3">
                                <div>
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $member->user?->name ?? 'Member' }}</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $member->fitness_goal ?: 'Goal not set' }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-status-badge :label="$member->membership_status ?? 'Active'" />
                                    <x-status-badge :label="$member->branch?->name ?? 'No branch'" tone="info" />
                                </div>
                            </div>
                        @empty
                            <x-empty-state title="No members assigned" message="Use the assignment panel to connect members to this trainer." />
                        @endforelse
                    </div>
                </x-premium-card>
            </div>

            <div class="space-y-5">
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Assign Members</h3>
                    <p class="panel-section-copy">Assign selected members to this trainer. Branch scope is enforced automatically.</p>

                    @if ($canManageTrainer)
                        <form action="{{ route('web.gym.trainers.assign-members', ['trainer' => $trainer->id] + request()->query()) }}" method="POST" class="mt-5 space-y-4">
                            @csrf
                            <div>
                                <label for="member_ids" class="panel-label">Select Members</label>
                                <select id="member_ids" name="member_ids[]" class="panel-select min-h-52" multiple>
                                    @foreach ($assignableMembers as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }} • {{ $member->memberProfile?->fitness_goal ?: 'No goal' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-action-button type="submit" variant="primary">Assign Members</x-action-button>
                        </form>
                    @else
                        <x-empty-state title="Assignment locked" message="You do not have permission to change trainer assignments in this scope." />
                    @endif
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Performance Snapshot</h3>
                    <p class="panel-section-copy">Live operational signals from assigned members and trainer activity.</p>
                    <div class="mt-4 grid gap-3">
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Assigned member visits this month</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $performanceSnapshot['monthly_attendance_count'] ?? 0 }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $performanceSnapshot['members_with_attendance'] ?? 0 }} assigned members checked in.</p>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">30-day trainer activity</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $performanceSnapshot['recent_activity_count'] ?? 0 }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Profile edits, assignment updates, and scoped audit events.</p>
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Recent Activity</h3>
                    <div class="mt-4 space-y-4">
                        @forelse ($activityTimeline as $event)
                            <div class="border-l border-slate-200 pl-4 dark:border-white/10">
                                <p class="font-medium text-slate-950 dark:text-white">{{ $event['title'] }}</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $event['change_summary'] }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $event['changed_by'] }} • {{ $event['date'] }}</p>
                            </div>
                        @empty
                            <x-empty-state title="No activity yet" message="Trainer changes and assignments will appear here." />
                        @endforelse
                    </div>
                </x-premium-card>
            </div>
        </div>
    </div>
@endsection
