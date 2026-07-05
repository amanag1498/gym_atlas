@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.trainer-specializations.index') }}" variant="secondary">Back to Specializations</x-action-button>
    @endsection

    <div class="space-y-6">
        @include('web.admin.trainer-specializations._form')
    </div>
@endsection
