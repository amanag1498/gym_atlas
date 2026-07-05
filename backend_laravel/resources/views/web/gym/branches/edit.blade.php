@extends('layouts.panel')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1.18fr_0.82fr]">
        <x-premium-card class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="panel-section-title">Edit {{ $branch->name }}</h3>
                    <p class="panel-section-copy">Update the operating profile, discovery signals, and service configuration for this branch.</p>
                </div>
                <x-status-badge :label="$branch->is_active ? 'Active' : 'Inactive'" />
            </div>

            <form action="{{ route('web.gym.branches.update', ['branch' => $branch->id, 'gym' => $gym->id]) }}" method="POST" class="mt-6 space-y-5">
                @csrf
                @method('PUT')
                @include('web.gym.branches._form', ['branch' => $branch, 'gym' => $gym, 'facilities' => $facilities, 'cities' => $cities])

                <div class="flex flex-wrap justify-end gap-3">
                    <x-action-button type="submit" variant="primary">Save Branch</x-action-button>
                    <x-action-button as="a" href="{{ route('web.gym.branches.show', ['branch' => $branch->id, 'gym' => $gym->id]) }}" variant="secondary">View Details</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <div class="space-y-5">
            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Operational Snapshot</h3>
                <div class="mt-4 grid gap-3">
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Members</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $branch->member_profiles_count }}</span>
                    </div>
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Trainers</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $branch->trainer_profiles_count }}</span>
                    </div>
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Plans</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $branch->membership_plans_count }}</span>
                    </div>
                    <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Today check-ins</span>
                        <span class="font-semibold text-slate-950 dark:text-white">{{ $branch->today_check_ins_count }}</span>
                    </div>
                </div>
            </x-premium-card>

            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Current Service Footprint</h3>
                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse ($branch->facilities as $facility)
                        <x-status-badge :label="$facility->name" tone="info" />
                    @empty
                        <span class="text-sm text-slate-500 dark:text-slate-400">No facilities selected yet.</span>
                    @endforelse
                </div>
            </x-premium-card>
        </div>
    </div>
@endsection
