@extends('layouts.panel')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1.16fr_0.84fr]">
        <x-premium-card class="p-6">
            <div class="mb-6">
                <h3 class="panel-section-title">Create Trainer</h3>
                <p class="panel-section-copy">Create a new trainer profile or attach an existing trainer user to this gym and branch.</p>
                <div class="mt-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">
                    Password is not required here. Existing users keep their current credentials.
                </div>
            </div>

            <form action="{{ route('web.gym.trainers.store', request()->query()) }}" method="POST" class="space-y-6">
                @csrf
                @include('web.gym.trainers._form')
                <div class="flex justify-end gap-3">
                    <a href="{{ route('web.gym.trainers.index', request()->query()) }}" class="panel-btn-secondary">Cancel</a>
                    <x-action-button type="submit" variant="primary">Create Trainer</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-premium-card class="p-6">
            <h3 class="panel-section-title">Setup Guidance</h3>
            <div class="mt-4 space-y-3">
                <div class="panel-card-muted px-4 py-3">
                    <p class="font-medium text-slate-950 dark:text-white">Branch mapping matters</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Member assignment and reporting follow the trainer branch scope.</p>
                </div>
                <div class="panel-card-muted px-4 py-3">
                    <p class="font-medium text-slate-950 dark:text-white">Use structured skill tags</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Languages, certifications, and specializations are easier to audit when entered one per line.</p>
                </div>
            </div>
        </x-premium-card>
    </div>
@endsection
