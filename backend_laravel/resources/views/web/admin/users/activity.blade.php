@extends('layouts.panel')

@section('content')
    @include('web.admin.partials.activity-history-table', [
        'heading' => $userDetail->name.' Activity History',
        'description' => 'Complete paginated audit history for this user, trainer, or member account.',
        'backUrl' => route('web.admin.users.show', $userDetail),
        'backLabel' => 'Back to Profile',
        'actionUrl' => route('web.admin.users.activity', $userDetail),
        'auditLogs' => $auditLogs,
        'filters' => $filters,
        'sanitizer' => $sanitizer,
    ])
@endsection
