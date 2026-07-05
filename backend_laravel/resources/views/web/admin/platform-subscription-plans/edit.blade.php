@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.gym-platform-subscriptions.create', ['plan' => $plan->id]) }}">Assign to Gym</x-action-button>
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.platform-subscription-plans.index') }}">All Plans</x-action-button>
    @endsection

    <form method="POST" action="{{ route('web.admin.platform-subscription-plans.update', $plan) }}" class="space-y-6">
        @csrf
        @method('PUT')
        @include('web.admin.platform-subscription-plans._form', ['submitLabel' => 'Save Plan'])
    </form>
@endsection
