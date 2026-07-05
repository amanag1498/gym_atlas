@extends('layouts.panel')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1.16fr_0.84fr]">
        <x-premium-card class="p-6">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="panel-section-title">Edit Trainer</h3>
                    <p class="panel-section-copy">Update branch scope, coaching identity, and readiness signals for this trainer.</p>
                </div>
                <x-status-badge :label="$trainerProfile?->status ?? 'Active'" />
            </div>

            <form action="{{ route('web.gym.trainers.update', ['trainer' => $trainer->id] + request()->query()) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                @include('web.gym.trainers._form')
                <div class="flex justify-end gap-3">
                    <a href="{{ route('web.gym.trainers.show', ['trainer' => $trainer->id] + request()->query()) }}" class="panel-btn-secondary">Cancel</a>
                    <x-action-button type="submit" variant="primary">Save Trainer</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-premium-card class="p-6">
            <h3 class="panel-section-title">Current Snapshot</h3>
            <div class="mt-4 grid gap-3">
                <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-500 dark:text-slate-400">Branch</span>
                    <span class="font-medium text-slate-950 dark:text-white">{{ $trainerProfile?->branch?->name ?? 'Gym-wide' }}</span>
                </div>
                <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-500 dark:text-slate-400">Assigned members</span>
                    <span class="font-medium text-slate-950 dark:text-white">{{ $trainer->assignedMembers->count() }}</span>
                </div>
                <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-500 dark:text-slate-400">Verification</span>
                    <span class="font-medium text-slate-950 dark:text-white">{{ $trainerProfile?->verification_status ?: 'Unverified' }}</span>
                </div>
            </div>
        </x-premium-card>
    </div>
@endsection
