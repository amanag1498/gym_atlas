@extends('layouts.panel')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1.16fr_0.84fr]">
        <x-premium-card class="p-6">
            <div>
                <h3 class="panel-section-title">Create Staff Member</h3>
                <p class="panel-section-copy">Create a branch manager or staff account, or attach an existing user and define their operational scope from the start.</p>
            </div>

            <form action="{{ route('web.gym.staff.store', ['gym' => $gym->id]) }}" method="POST" class="mt-6 space-y-5">
                @csrf
                @include('web.gym.staff._form')

                <div class="flex flex-wrap justify-end gap-3">
                    <a href="{{ route('web.gym.staff.index', ['gym' => $gym->id]) }}" class="panel-btn-secondary">Cancel</a>
                    <x-action-button type="submit" variant="primary">Create Staff Member</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-premium-card class="p-6">
            <h3 class="panel-section-title">Intake Guidance</h3>
            <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                <div class="panel-card-muted px-4 py-3">Attach an existing user if the account already signs in elsewhere and only needs gym access here.</div>
                <div class="panel-card-muted px-4 py-3">Branch managers should be mapped only to branches they operationally control.</div>
                <div class="panel-card-muted px-4 py-3">Gym staff defaults come from Settings and can be tightened per user before saving.</div>
            </div>
        </x-premium-card>
    </div>
@endsection
