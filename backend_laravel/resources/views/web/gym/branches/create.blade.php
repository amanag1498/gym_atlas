@extends('layouts.panel')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1.18fr_0.82fr]">
        <x-premium-card class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="panel-section-title">Create Branch</h3>
                    <p class="panel-section-copy">Set the branch identity, operating schedule, discovery data, and service footprint.</p>
                </div>
                <x-status-badge label="New branch" tone="info" />
            </div>

            <form action="{{ route('web.gym.branches.store', ['gym' => $gym->id]) }}" method="POST" class="mt-6 space-y-5">
                @csrf
                @include('web.gym.branches._form', ['branch' => null, 'gym' => $gym, 'facilities' => $facilities, 'cities' => $cities])

                <div class="flex flex-wrap justify-end gap-3">
                    <a href="{{ route('web.gym.branches.index', ['gym' => $gym->id]) }}" class="panel-btn-secondary">Cancel</a>
                    <x-action-button type="submit" variant="primary">Create Branch</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-premium-card class="p-6">
            <h3 class="panel-section-title">Recommended Setup</h3>
            <div class="mt-4 grid gap-3">
                <div class="panel-card-muted px-4 py-3">
                    <p class="font-medium text-slate-950 dark:text-white">Location first</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Link the city record and keep a clean address so listings and branch filters stay consistent.</p>
                </div>
                <div class="panel-card-muted px-4 py-3">
                    <p class="font-medium text-slate-950 dark:text-white">Use split schedules</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Morning and evening windows are supported per day, including closed days.</p>
                </div>
                <div class="panel-card-muted px-4 py-3">
                    <p class="font-medium text-slate-950 dark:text-white">Curate photos</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">A smaller set of clean branch images works better than a noisy gallery.</p>
                </div>
            </div>
        </x-premium-card>
    </div>
@endsection
