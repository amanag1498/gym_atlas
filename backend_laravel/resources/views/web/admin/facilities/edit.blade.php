@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        @include('web.admin.facilities._form', ['facility' => $facility])
    </div>
@endsection
