@extends('layouts.panel')

@section('content')
    <x-premium-card class="p-6">
        <div class="mb-6">
            <h3 class="panel-section-title">Create Member</h3>
            <p class="panel-section-copy">Create a member profile for Google/Firebase login, or convert an existing user with branch and trainer assignment.</p>
            <div class="mt-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                Existing app users are not added immediately. They receive a gym invitation and must accept it before the member profile becomes active for this gym.
            </div>
        </div>

        <form action="{{ route('web.gym.members.store', request()->query()) }}" method="POST" class="space-y-6">
            @csrf
            @include('web.gym.members._form')
            <div class="flex justify-end gap-3">
                <a href="{{ route('web.gym.members.index', request()->query()) }}" class="panel-btn-secondary">Cancel</a>
                <x-action-button type="submit" variant="primary">Create Member</x-action-button>
            </div>
        </form>
    </x-premium-card>
@endsection
