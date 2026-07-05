@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        @include('web.admin.exercises._form', ['exercise' => $exercise, 'statusOptions' => $statusOptions])
    </div>
@endsection
