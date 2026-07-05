@extends('layouts.panel')

@section('content')
    <div class="grid gap-5 xl:grid-cols-[1.14fr_0.86fr]">
        <x-premium-card class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="panel-section-title">Edit {{ $staff->name }}</h3>
                    <p class="panel-section-copy">Update role, branch scope, and delegated permissions for this staff account.</p>
                </div>
                <x-status-badge :label="$staff->is_active ? 'Active' : 'Inactive'" />
            </div>

            <form action="{{ route('web.gym.staff.update', ['staff' => $staff->id, 'gym' => $gym->id]) }}" method="POST" class="mt-6 space-y-5">
                @csrf
                @method('PUT')
                @include('web.gym.staff._form', ['staff' => $staff])

                <div class="flex flex-wrap gap-3">
                    <x-action-button type="submit" variant="primary">Save Staff</x-action-button>
                    <x-action-button as="a" href="{{ route('web.gym.staff.show', ['staff' => $staff->id, 'gym' => $gym->id]) }}" variant="secondary">View Detail</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-premium-card class="p-6">
            <h3 class="panel-section-title">Current Access</h3>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($currentPermissions as $permission)
                    <x-status-badge :label="$permissionToggles[$permission] ?? str($permission)->replace('_', ' ')->title()" tone="success" />
                @endforeach
                @if ($currentPermissions === [])
                    <span class="text-sm text-slate-500 dark:text-slate-400">No custom permissions enabled.</span>
                @endif
            </div>
        </x-premium-card>
    </div>
@endsection
