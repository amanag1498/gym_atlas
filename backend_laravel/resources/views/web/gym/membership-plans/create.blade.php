@extends('layouts.panel')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1.16fr_0.84fr]">
        <x-premium-card class="p-6">
            <div class="mb-6">
                <h3 class="panel-section-title">Create Membership Plan</h3>
                <p class="panel-section-copy">Set the branch scope and base pricing once. Existing member memberships will continue using their copied snapshot even if you later edit this plan.</p>
            </div>

            <form action="{{ route('web.gym.membership-plans.store', request()->query()) }}" method="POST" class="space-y-6">
                @csrf
                @include('web.gym.membership-plans._form')
                <div class="flex justify-end gap-3">
                    <a href="{{ route('web.gym.membership-plans.index', request()->query()) }}" class="panel-btn-secondary">Cancel</a>
                    <x-action-button type="submit" variant="primary">Create Plan</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-premium-card class="p-6">
            <h3 class="panel-section-title">Suggested Catalogue</h3>
            <div class="mt-4 space-y-3">
                @foreach (['Free trial access', 'Monthly standard', 'Quarterly saver', 'Half-year transformation', 'Yearly elite', 'PT premium'] as $example)
                    <div class="panel-card-muted px-4 py-3">
                        <p class="font-medium text-slate-950 dark:text-white">{{ $example }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use clean cadence-based names so billing staff can assign the right plan instantly.</p>
                    </div>
                @endforeach
            </div>
        </x-premium-card>
    </div>
@endsection
