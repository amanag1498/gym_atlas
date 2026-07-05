@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        @include('web.admin.banners._form', ['banner' => $banner])
    </div>
@endsection
