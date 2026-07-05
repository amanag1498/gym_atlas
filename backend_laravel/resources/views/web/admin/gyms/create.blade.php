@extends('layouts.panel')

@section('page_actions')
    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.index') }}">Cancel</x-action-button>
    <x-action-button as="button" type="submit" form="gym-form">Create Gym</x-action-button>
@endsection

@section('content')
    @include('web.admin.gyms._form')
@endsection
