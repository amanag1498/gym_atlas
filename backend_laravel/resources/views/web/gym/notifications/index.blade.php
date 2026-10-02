@extends('layouts.panel')

@php($panelFullWidth = true)

@section('content')
    <div class="w-full min-w-0 space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
            <div class="min-w-0">
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Notifications</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Recent updates for this gym.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <x-status-badge :label="$unreadNotificationsCount . ' unread'" :tone="$unreadNotificationsCount > 0 ? 'warning' : 'neutral'" />
                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.announcements.index', request()->only(['gym', 'branch'])) }}">Announcements</x-action-button>
            </div>
        </header>

        <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-label="Notification list">
            @if ($notifications->count() === 0)
                <div class="p-5 sm:p-7">
                    <x-empty-state title="No notifications yet" message="Announcements, memberships, and attendance updates will appear here." />
                </div>
            @else
                <div class="divide-y divide-slate-200 md:hidden dark:divide-slate-800">
                    @foreach ($notifications as $notification)
                        <article class="p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="min-w-0 break-words text-sm font-semibold text-slate-950 dark:text-slate-100">{{ $notification->title }}</h3>
                                <x-status-badge :label="$notification->read_at ? 'Read' : 'Unread'" :tone="$notification->read_at ? 'success' : 'warning'" />
                            </div>
                            <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $notification->body ?: $notification->message }}</p>
                            <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
                                <span>{{ str_replace('_', ' ', ucfirst($notification->type)) }}</span>
                                <time>{{ optional($notification->created_at)->format('d M Y, h:i A') ?: 'Date unavailable' }}</time>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="panel-table w-full min-w-[680px]">
                        <thead><tr><th>Notification</th><th>Type</th><th>Status</th><th>Received</th></tr></thead>
                        <tbody>
                            @foreach ($notifications as $notification)
                                <tr>
                                    <td class="min-w-0">
                                        <p class="break-words font-semibold text-slate-950 dark:text-slate-100">{{ $notification->title }}</p>
                                        <p class="mt-1 max-w-2xl whitespace-pre-wrap break-words text-sm text-slate-600 dark:text-slate-400">{{ $notification->body ?: $notification->message }}</p>
                                    </td>
                                    <td>{{ str_replace('_', ' ', ucfirst($notification->type)) }}</td>
                                    <td><x-status-badge :label="$notification->read_at ? 'Read' : 'Unread'" :tone="$notification->read_at ? 'success' : 'warning'" /></td>
                                    <td class="whitespace-nowrap">{{ optional($notification->created_at)->format('d M Y, h:i A') ?: 'Date unavailable' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-4 py-4 dark:border-slate-800">{{ $notifications->links() }}</div>
            @endif
        </section>
    </div>
@endsection
