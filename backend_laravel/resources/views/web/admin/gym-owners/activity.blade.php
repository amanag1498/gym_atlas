@extends('layouts.panel')

@section('content')
    @include('web.admin.partials.activity-history-table', [
        'heading' => $owner->name.' Activity History',
        'description' => 'Complete paginated audit history across this owner and their owned gym footprint.',
        'backUrl' => route('web.admin.gym-owners.show', $owner),
        'backLabel' => 'Back to Owner',
        'actionUrl' => route('web.admin.gym-owners.activity', $owner),
        'auditLogs' => $auditLogs,
        'filters' => $filters,
        'sanitizer' => $sanitizer,
    ])
@endsection
