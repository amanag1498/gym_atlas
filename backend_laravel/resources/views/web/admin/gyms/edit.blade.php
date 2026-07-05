@extends('layouts.panel')

@section('page_actions')
    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.index') }}">Back</x-action-button>
    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.show', $gym) }}">View Gym</x-action-button>
    <x-action-button as="button" type="submit" form="gym-form">Save Changes</x-action-button>
@endsection

@section('content')
    @include('web.admin.gyms._form')
@endsection
