@extends('layouts.panel')

@php
    $panelFullWidth = true;
@endphp

@section('content')
    @php
        $activeTab = $errors->any() ? 'compose' : (request('tab') === 'history' || request()->hasAny(['search', 'audience_type', 'announcements_page']) ? 'history' : 'compose');
    @endphp
    <div class="w-full min-w-0 space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Member announcements</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Send updates and review their delivery.</p>
            </div>
            <x-action-button as="a" variant="secondary" href="{{ route('web.gym.notifications.index', request()->only(['gym', 'branch'])) }}">Notifications · {{ $unreadNotificationsCount }} unread</x-action-button>
        </header>

        <nav class="flex gap-7 border-b border-slate-200 dark:border-slate-800" aria-label="Announcement views">
            <a href="{{ route('web.gym.announcements.index', array_merge(request()->only(['gym', 'branch']), ['tab' => 'compose'])) }}" @if ($activeTab === 'compose') aria-current="page" @endif class="inline-flex min-h-12 items-center border-b-2 px-1 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 {{ $activeTab === 'compose' ? 'border-brand-500 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-600 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white' }}">Compose</a>
            <a href="{{ route('web.gym.announcements.index', array_merge(request()->only(['gym', 'branch']), ['tab' => 'history'])) }}" @if ($activeTab === 'history') aria-current="page" @endif class="inline-flex min-h-12 items-center gap-2 border-b-2 px-1 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 {{ $activeTab === 'history' ? 'border-brand-500 text-brand-700 dark:text-brand-300' : 'border-transparent text-slate-600 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white' }}">History <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $announcementSummary['total'] }}</span></a>
        </nav>

        @if ($activeTab === 'compose')
        <section aria-label="Compose announcement">
            <div class="w-full min-w-0">
            <x-premium-card id="create-announcement" class="p-5 sm:p-7 lg:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700 dark:text-sky-300">New message</p>
                        <h3 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Create announcement</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Choose who should receive this update, then write your message.</p>
                    </div>
                    <span class="inline-flex self-start rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">{{ $gym->name }}</span>
                </div>

                @if ($canSendAnnouncements)
                <form action="{{ route('web.gym.announcements.store', request()->only(['gym', 'branch'])) }}" method="POST" class="mt-6 space-y-5" data-announcement-composer data-member-search-url="{{ route('web.gym.announcements.member-options', ['gym' => $gym->id]) }}">
                @csrf
                <input type="hidden" name="gym_id" value="{{ $gym->id }}">
                <div class="border-t border-slate-200 pt-5 dark:border-slate-700"><p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-600 dark:text-slate-400">1 · Recipients</p>
                <x-form-select id="send_audience_type" name="audience_type" label="Send to" :selected="old('audience_type', array_key_first($sendAudienceOptions))" data-gym-announcement-audience>
                    @foreach ($sendAudienceOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('audience_type', array_key_first($sendAudienceOptions)) === $value)>{{ $label }}</option>
                    @endforeach
                </x-form-select>
                <div data-gym-announcement-branch class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                <x-form-select id="announcement_branch_id" name="branch_id" label="Branch" :selected="old('branch_id', $branch?->id)">
                    <option value="">All eligible branches</option>
                    @foreach ($branches as $branchOption)
                        <option value="{{ $branchOption->id }}" @selected((string) old('branch_id', $branch?->id) === (string) $branchOption->id)>{{ $branchOption->name }}</option>
                    @endforeach
                </x-form-select>
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-400" data-branch-help>Choose one branch for a branch announcement, or narrow a selected-member message.</p>
                @error('branch_id')<p class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</p>@enderror
                </div>
                <div data-gym-announcement-members class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-950 dark:text-slate-100">Selected members</p>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Browse or search, then tap members to add them.</p>
                        </div>
                        <span data-member-count class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300" aria-live="polite">{{ $selectedMembers->count() }} selected</span>
                    </div>
                    <div data-selected-member-chips class="flex flex-wrap gap-2" aria-live="polite">
                        @foreach ($selectedMembers as $member)
                            <span data-member-chip data-member-id="{{ $member->id }}" data-member-name="{{ $member->name }}" data-member-email="{{ $member->email }}" class="inline-flex max-w-full items-center gap-2 rounded-full border border-brand-200 bg-white py-1 pl-3 pr-1 text-xs font-medium text-slate-800 dark:border-brand-500/30 dark:bg-slate-900 dark:text-slate-100">
                                <span class="truncate">{{ $member->name }}</span>
                                <button type="button" data-remove-member="{{ $member->id }}" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-slate-300 dark:hover:bg-slate-700" aria-label="Remove {{ $member->name }}">&times;</button>
                            </span>
                        @endforeach
                    </div>
                    <p data-member-empty class="text-sm text-slate-600 dark:text-slate-400 {{ $selectedMembers->isNotEmpty() ? 'hidden' : '' }}">No members selected yet.</p>
                    <div data-selected-member-inputs>
                        @foreach ($selectedMembers as $member)<input type="hidden" name="member_ids[]" value="{{ $member->id }}">@endforeach
                    </div>
                    <button type="button" data-open-member-picker aria-haspopup="dialog" aria-controls="announcement-member-picker" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-300 bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:border-brand-500/40 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-slate-800">
                        <i class="ti ti-users-plus" aria-hidden="true"></i> Choose members
                    </button>
                    @error('member_ids')<div class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>@enderror
                </div>
                </div>
                <div class="border-t border-slate-200 pt-5 dark:border-slate-700"><p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-600 dark:text-slate-400">2 · Message</p>
                <x-form-input name="title" label="Title" :value="old('title')" placeholder="Announcement title" required />
                <div class="mt-4">
                    <label for="announcement_message" class="panel-label">Message</label>
                    <textarea id="announcement_message" name="message" class="panel-textarea" rows="6" placeholder="Write the member update" required>{{ old('message') }}</textarea>
                    @error('message')<div class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>@enderror
                </div>
                </div>
                <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-4 dark:border-brand-500/25 dark:bg-brand-500/10">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-700 dark:text-brand-300">Ready to send?</p>
                    <p data-audience-summary class="mt-1 text-sm text-slate-700 dark:text-slate-200" aria-live="polite"></p>
                    <x-action-button type="submit" variant="primary" class="mt-4 w-full justify-center">Send announcement</x-action-button>
                </div>
                <dialog id="announcement-member-picker" aria-labelledby="announcement-member-picker-title" class="fixed inset-x-0 bottom-0 m-0 max-h-[90dvh] w-full max-w-2xl overflow-hidden rounded-t-3xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/65 sm:m-auto sm:rounded-3xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    <div class="flex max-h-[90dvh] flex-col">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-4 sm:p-5 dark:border-slate-700">
                            <div>
                                <h4 id="announcement-member-picker-title" class="text-lg font-semibold">Choose members</h4>
                                <p data-member-picker-scope class="mt-1 text-sm text-slate-600 dark:text-slate-400">Browse eligible members.</p>
                            </div>
                            <button type="button" data-close-member-picker class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Close member picker"><i class="ti ti-x text-xl" aria-hidden="true"></i></button>
                        </div>
                        <div class="px-4 pt-4 sm:px-5">
                            <label for="announcement-member-search" class="panel-label">Search by name or email</label>
                            <input id="announcement-member-search" data-member-search type="search" class="panel-input" placeholder="Browse or type at least 2 characters" autocomplete="off">
                        </div>
                        <div data-member-results class="min-h-32 flex-1 space-y-2 overflow-y-auto px-4 py-4 sm:px-5" aria-live="polite">
                            <p class="text-sm text-slate-600 dark:text-slate-400">Open the picker to browse eligible members.</p>
                        </div>
                        <div class="border-t border-slate-200 p-4 sm:p-5 dark:border-slate-700">
                            <button type="button" data-member-load-more class="mb-3 hidden min-h-11 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Load more members</button>
                            <div class="flex items-center justify-between gap-3">
                                <span data-member-dialog-count class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ $selectedMembers->count() }} selected</span>
                                <button type="button" data-close-member-picker class="panel-btn-primary">Done</button>
                            </div>
                        </div>
                    </div>
                </dialog>
                </form>
                @else
                    <x-empty-state title="Announcement sending disabled" message="Your current role can view announcement history, but sending announcements requires additional permission in this scope." />
                @endif
            </x-premium-card>
            </div>
        </section>

        @else
        <section aria-label="Announcement history" class="space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-slate-950 dark:text-white">Sent announcements</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Search your past messages and check who read them.</p>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ $announcementSummary['total'] }} messages · {{ $announcementSummary['recipients'] }} deliveries · {{ $announcementSummary['read_recipients'] }} read</p>
            </div>

            <x-premium-card class="p-5">
                <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_240px_auto] md:items-end">
                    <input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}">
                    <input type="hidden" name="tab" value="history">
                    @if (request()->filled('branch'))
                        <input type="hidden" name="branch" value="{{ request('branch') }}">
                    @endif
                    <x-form-input name="search" label="Search announcements" :value="request('search')" placeholder="Title or message" />
                    <x-form-select name="audience_type" label="Audience" :selected="request('audience_type')" :options="$audienceOptions" />
                    <div class="flex gap-2">
                        <x-action-button type="submit">Apply</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.announcements.index', array_merge(request()->only(['gym', 'branch']), ['tab' => 'history'])) }}">Reset</x-action-button>
                    </div>
                </form>
            </x-premium-card>

            <x-table-wrapper class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="panel-section-title">Messages</h3>
                    </div>
                    <x-status-badge :label="$announcements->total() . ' visible'" tone="neutral" />
                </div>
                <div class="mt-6 space-y-3 md:hidden">
                    @forelse ($announcements as $announcement)
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h4 class="min-w-0 flex-1 break-words text-sm font-semibold text-slate-950 dark:text-slate-100">{{ $announcement->title }}</h4>
                                <x-status-badge :label="str_replace('_', ' ', ucfirst($announcement->audience_type))" tone="info" />
                            </div>
                            <p class="mt-2 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ $announcement->message }}</p>
                            <p class="mt-3 text-xs text-slate-600 dark:text-slate-400">{{ $announcement->recipients_count }} recipients · {{ $announcement->read_recipients_count }} read · {{ optional($announcement->send_at)->format('d M Y H:i') ?: 'Date unavailable' }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <x-action-button as="a" variant="secondary" href="{{ route('web.gym.announcements.show', array_merge(request()->only(['gym', 'branch']), ['announcement' => $announcement->id])) }}">View details</x-action-button>
                                @if ($canSendAnnouncements)
                                    <form method="POST" action="{{ route('web.gym.announcements.destroy', array_merge(request()->only(['gym', 'branch']), ['announcement' => $announcement->id])) }}" data-confirm-submit data-confirm-title="Delete announcement?" data-confirm-message="The announcement and its linked notifications will be permanently removed." data-confirm-button="Delete announcement">
                                        @csrf
                                        @method('DELETE')
                                        <x-action-button type="submit" variant="danger">Delete</x-action-button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <x-empty-state title="No announcements yet" message="Open Compose to send your first update to members." />
                    @endforelse
                </div>
                <div class="mt-6 hidden overflow-x-auto md:block">
            <table class="panel-table">
                <thead><tr><th>Title</th><th>Audience</th><th>Delivery</th><th>Sent</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($announcements as $announcement)
                    <tr>
                        <td>
                            <div class="font-medium text-slate-950 dark:text-slate-100">{{ $announcement->title }}</div>
                            <div class="mt-1 max-w-sm truncate text-xs text-slate-600 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($announcement->message, 90) }}</div>
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
                                :action-href="route('web.gym.announcements.index', array_merge(request()->only(['gym', 'branch']), ['tab' => 'compose'])).'#create-announcement'"
                            />
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
                </div>
            <div class="mt-6">{{ $announcements->links() }}</div>
            </x-table-wrapper>
        </section>
        @endif
    </div>
@endsection
