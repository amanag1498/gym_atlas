@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.index') }}">Gym Billing</x-action-button>
    @endsection

    <form method="POST" action="{{ route('web.admin.platform-subscription-plans.store') }}" class="space-y-6">
        @csrf
        @include('web.admin.platform-subscription-plans._form', ['submitLabel' => 'Create Plan'])
    </form>
@endsection
