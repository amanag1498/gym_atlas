@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Notification center</p>
                    <h3 class="mt-3 text-3xl font-semibold tracking-tight text-white">Notifications</h3>
                    <p class="mt-3 max-w-2xl text-sm text-slate-300">Track personal gym notifications, unread items, and announcement-related alerts inside the current scope.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-status-badge :label="$unreadNotificationsCount . ' unread'" :tone="$unreadNotificationsCount > 0 ? 'warning' : 'neutral'" />
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.announcements.index', request()->only(['gym', 'branch'])) }}">Announcements</x-action-button>
                </div>
            </div>
        </section>

        <x-table-wrapper>
            <table class="panel-table">
                <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                @forelse ($notifications as $notification)
                    <tr>
                        <td>
                            <div class="font-medium text-white">{{ $notification->title }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $notification->message }}</div>
                        </td>
                        <td><x-status-badge :label="str_replace('_', ' ', $notification->type)" tone="info" /></td>
                        <td><x-status-badge :label="$notification->read_at ? 'Read' : 'Unread'" :tone="$notification->read_at ? 'success' : 'warning'" /></td>
                        <td>{{ optional($notification->created_at)->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><x-empty-state title="No notifications yet" message="Notifications generated from announcements, memberships, and attendance alerts will appear here." /></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
            <div class="mt-6">{{ $notifications->links() }}</div>
        </x-table-wrapper>
    </div>
@endsection
