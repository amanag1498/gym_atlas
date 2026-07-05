@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <div class="grid gap-4 lg:grid-cols-4">
            <x-stat-card label="Announcements" :value="$announcements->total()" hint="Messages sent in current scope" tone="sky" />
            <x-stat-card label="Gym-wide" :value="$announcements->getCollection()->where('audience_type', 'gym_wide')->count()" hint="Broadcasts to all gym members on this page" tone="emerald" />
            <x-stat-card label="Branch-specific" :value="$announcements->getCollection()->where('audience_type', 'branch_specific')->count()" hint="Scoped member updates" tone="violet" />
            <x-stat-card label="Selected Members" :value="$announcements->getCollection()->where('audience_type', 'selected_members')->count()" hint="Direct target messages" tone="amber" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
            <x-premium-card id="create-announcement" class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Member communication</p>
                        <h3 class="mt-3 text-2xl font-semibold tracking-tight text-white">Create Announcement</h3>
                        <p class="mt-2 text-sm text-slate-400">Send gym-wide, branch-specific, or selected-member announcements from a single communication surface.</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <x-status-badge label="Notification Ready" tone="info" />
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.notifications.index', request()->only(['gym', 'branch'])) }}">Notifications ({{ $unreadNotificationsCount }})</x-action-button>
                    </div>
                </div>

                @if ($canSendAnnouncements)
                <form action="{{ route('web.gym.announcements.store') }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="gym_id" value="{{ $gym->id }}">
                <x-form-select name="audience_type" label="Audience Type">
                    <option value="gym_wide">All Gym Members</option>
                    <option value="branch_specific">Branch Members</option>
                    <option value="selected_members">Selected Members</option>
                </x-form-select>
                <x-form-select name="branch_id" label="Branch Scope">
                    <option value="">No branch scope</option>
                    @foreach ($branches as $branchOption)
                        <option value="{{ $branchOption->id }}" @selected($branch?->id === $branchOption->id)>{{ $branchOption->name }}</option>
                    @endforeach
                </x-form-select>
                <x-form-input name="title" label="Title" placeholder="Announcement title" required />
                <textarea name="message" class="panel-textarea" placeholder="Message" required></textarea>
                <div>
                    <label class="panel-label">Selected Members</label>
                    <select name="member_ids[]" class="panel-select" multiple size="6">
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Only used when the audience type is set to selected members.</p>
                </div>
                <x-action-button type="submit" variant="primary" class="w-full justify-center">Send Announcement</x-action-button>
                </form>
                @else
                    <x-empty-state title="Announcement sending disabled" message="Your current role can view announcement history, but sending announcements requires additional permission in this scope." />
                @endif
            </x-premium-card>

            <x-table-wrapper>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="panel-section-title">Announcement history</h3>
                        <p class="panel-section-copy">Track who received each announcement and how broad the audience was.</p>
                    </div>
                    <x-status-badge :label="$announcements->total() . ' visible'" tone="neutral" />
                </div>
                <div class="mt-6 overflow-x-auto">
            <table class="panel-table">
                <thead><tr><th>Title</th><th>Audience</th><th>Recipients</th><th>Sent</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($announcements as $announcement)
                    <tr>
                        <td>
                            <div class="font-medium text-white">{{ $announcement->title }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($announcement->message, 90) }}</div>
                        </td>
                        <td><x-status-badge :label="str_replace('_', ' ', ucfirst($announcement->audience_type))" tone="info" /></td>
                        <td>{{ $announcement->recipients_count }}</td>
                        <td>{{ optional($announcement->send_at)->format('d M Y H:i') }}</td>
                        <td>
                            <div class="flex justify-end gap-2">
                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.announcements.show', array_merge(request()->only(['gym', 'branch']), ['announcement' => $announcement->id])) }}">View</x-action-button>
                                @if ($canSendAnnouncements)
                                    <form method="POST" action="{{ route('web.gym.announcements.destroy', array_merge(request()->only(['gym', 'branch']), ['announcement' => $announcement->id])) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-action-button type="submit" variant="danger">Delete</x-action-button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state
                                title="No announcements yet"
                                message="Send your first update to members and branch audiences from here."
                                action-label="Create Announcement"
                                action-href="#create-announcement"
                            />
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
                </div>
            <div class="mt-6">{{ $announcements->links() }}</div>
            </x-table-wrapper>
        </div>

        <x-table-wrapper>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="panel-section-title">Recent notifications</h3>
                    <p class="panel-section-copy">Personal announcement and billing notifications inside the current gym scope.</p>
                </div>
                <x-status-badge :label="$unreadNotificationsCount . ' unread'" :tone="$unreadNotificationsCount > 0 ? 'warning' : 'neutral'" />
            </div>
            <div class="mt-6 overflow-x-auto">
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
                            <td colspan="4"><x-empty-state title="No notifications yet" message="Notifications generated from announcements and billing alerts will appear here." /></td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">{{ $notifications->links() }}</div>
        </x-table-wrapper>
    </div>
@endsection
