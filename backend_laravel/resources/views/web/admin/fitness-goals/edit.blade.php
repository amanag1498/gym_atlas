@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.fitness-goals.index') }}" variant="secondary">Back to Goals</x-action-button>
    @endsection

    <div class="space-y-6">
        @include('web.admin.fitness-goals._form')
    </div>
@endsection
