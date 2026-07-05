@extends('layouts.panel')

@section('content')
    <x-premium-card class="p-6">
        <div class="mb-6">
            <h3 class="panel-section-title">Edit Member</h3>
            <p class="panel-section-copy">Update member profile, trainer assignment, body metrics, and medical context without leaving the gym panel.</p>
        </div>

        <form action="{{ route('web.gym.members.update', ['member' => $member->id] + request()->query()) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            @include('web.gym.members._form')
            <div class="flex justify-end gap-3">
                <a href="{{ route('web.gym.members.show', ['member' => $member->id] + request()->query()) }}" class="panel-btn-secondary">Cancel</a>
                <x-action-button type="submit" variant="primary">Save Member</x-action-button>
            </div>
        </form>
    </x-premium-card>
@endsection
