@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <x-stat-card label="Announcements" :value="$announcementSummary['total']" hint="Messages matching this scope" tone="sky" />
            <x-stat-card label="Gym-wide" :value="$announcementSummary['gym_wide']" hint="Broadcasts to all gym members" tone="emerald" />
            <x-stat-card label="Branch-specific" :value="$announcementSummary['branch_specific']" hint="Scoped member updates" tone="violet" />
            <x-stat-card label="Selected Members" :value="$announcementSummary['selected_members']" hint="Direct target messages" tone="amber" />
            <x-stat-card label="Delivered" :value="$announcementSummary['recipients']" hint="Recipient records created" tone="info" />
            <x-stat-card label="Read" :value="$announcementSummary['read_recipients']" hint="Recipients who opened the notice" tone="success" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_240px_auto] md:items-end">
                <input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}">
                @if (request()->filled('branch'))
                    <input type="hidden" name="branch" value="{{ request('branch') }}">
                @endif
                <x-form-input name="search" label="Search announcements" :value="request('search')" placeholder="Title or message" />
                <x-form-select name="audience_type" label="Audience" :selected="request('audience_type')" :options="$audienceOptions" />
                <div class="flex gap-2">
                    <x-action-button type="submit">Apply</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.announcements.index', request()->only(['gym', 'branch'])) }}">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

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
                <x-form-select id="send_audience_type" name="audience_type" label="Audience Type" :selected="old('audience_type', 'gym_wide')" data-gym-announcement-audience>
                    @foreach ($sendAudienceOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('audience_type', array_key_first($sendAudienceOptions)) === $value)>{{ $label }}</option>
                    @endforeach
                </x-form-select>
                <div data-gym-announcement-branch>
                <x-form-select id="announcement_branch_id" name="branch_id" label="Branch Scope" :selected="old('branch_id', $branch?->id)">
                    <option value="">No branch scope</option>
                    @foreach ($branches as $branchOption)
                        <option value="{{ $branchOption->id }}" @selected((string) old('branch_id', $branch?->id) === (string) $branchOption->id)>{{ $branchOption->name }}</option>
                    @endforeach
                </x-form-select>
                </div>
                <x-form-input name="title" label="Title" :value="old('title')" placeholder="Announcement title" required />
                <div>
                    <label for="announcement_message" class="panel-label">Message</label>
                    <textarea id="announcement_message" name="message" class="panel-textarea" rows="6" placeholder="Write the member update" required>{{ old('message') }}</textarea>
                    @error('message')<div class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>@enderror
                </div>
                <div data-gym-announcement-members>
                    <label for="announcement_member_ids" class="panel-label">Selected Members</label>
                    <select id="announcement_member_ids" name="member_ids[]" class="panel-select min-h-[190px]" multiple size="7">
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected(collect(old('member_ids', []))->contains($member->id))>{{ $member->name }}{{ $member->email ? ' — '.$member->email : '' }}</option>
                    @endforeach
                    </select>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Use Ctrl/Cmd to select more than one member.</p>
                    @error('member_ids')<div class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>@enderror
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
                <thead><tr><th>Title</th><th>Audience</th><th>Delivery</th><th>Sent</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($announcements as $announcement)
                    <tr>
                        <td>
                            <div class="font-medium text-white">{{ $announcement->title }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($announcement->message, 90) }}</div>
                        </td>
                        <td><x-status-badge :label="str_replace('_', ' ', ucfirst($announcement->audience_type))" tone="info" /></td>
                        <td>
                            <div class="font-medium text-slate-950 dark:text-white">{{ $announcement->recipients_count }} recipients</div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $announcement->read_recipients_count }} read</div>
                        </td>
                        <td>{{ optional($announcement->send_at)->format('d M Y H:i') ?: 'Not available' }}</td>
                        <td>
                            <div class="flex justify-end gap-2">
                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.announcements.show', array_merge(request()->only(['gym', 'branch']), ['announcement' => $announcement->id])) }}">View</x-action-button>
                                @if ($canSendAnnouncements)
                                    <form method="POST" action="{{ route('web.gym.announcements.destroy', array_merge(request()->only(['gym', 'branch']), ['announcement' => $announcement->id])) }}" data-confirm-submit data-confirm-title="Delete announcement?" data-confirm-message="The announcement and its linked notifications will be permanently removed." data-confirm-button="Delete announcement">
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
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $notification->body ?: $notification->message }}</div>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const audience = document.querySelector('[data-gym-announcement-audience]');
            const branch = document.querySelector('[data-gym-announcement-branch]');
            const members = document.querySelector('[data-gym-announcement-members]');

            if (!audience || !branch || !members) return;

            const updateAudienceFields = () => {
                branch.classList.toggle('hidden', audience.value === 'gym_wide');
                members.classList.toggle('hidden', audience.value !== 'selected_members');
            };

            audience.addEventListener('change', updateAudienceFields);
            updateAudienceFields();
        });
    </script>
@endpush
