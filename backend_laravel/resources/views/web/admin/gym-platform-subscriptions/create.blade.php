@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.platform-subscription-plans.index') }}">Platform Plans</x-action-button>
    @endsection

    <form method="POST" action="{{ route('web.admin.gym-platform-subscriptions.store') }}" class="space-y-6">
        @csrf
        @include('web.admin.gym-platform-subscriptions._form', ['submitLabel' => 'Assign Subscription'])
    </form>
@endsection
