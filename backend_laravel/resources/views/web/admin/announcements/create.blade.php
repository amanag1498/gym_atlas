@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.announcements.index') }}" variant="secondary">Back to Announcements</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Broadcast Composer</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Send Announcement</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Compose platform or targeted operational notices with the same premium controls used across the rest of the admin surface.</p>
                </div>
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Audience Modes</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ count($audienceOptions) }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">scope options supported by backend</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Member Targets</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $members->count() }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">available for selected-member sends</div>
                    </div>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('web.admin.announcements.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_360px]">
            @csrf

            <x-premium-card class="p-5">
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-form-select name="audience_type" label="Audience Type" :selected="old('audience_type', \App\Enums\AnnouncementAudienceType::PlatformWide->value)" :options="$audienceOptions" data-announcement-audience />
                    </div>
                    <div class="md:col-span-2">
                        <x-form-input name="title" label="Title" :value="old('title')" placeholder="New platform update" required />
                    </div>
                    <div class="md:col-span-2">
                        <label class="panel-label" for="message">Message</label>
                        <textarea id="message" name="message" class="panel-textarea" rows="7" placeholder="Write the update users should receive..." required>{{ old('message') }}</textarea>
                        @error('message')
                            <div class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>
                        @enderror
                    </div>
                    <div data-announcement-gym class="hidden">
                        <label class="panel-label" for="gym_id">Gym</label>
                        <select id="gym_id" name="gym_id" class="panel-select">
                            <option value="">Select gym</option>
                            @foreach ($gyms as $gym)
                                <option value="{{ $gym->id }}" @selected((string) old('gym_id') === (string) $gym->id)>{{ $gym->name }}</option>
                            @endforeach
                        </select>
                        @error('gym_id')
                            <div class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>
                        @enderror
                    </div>
                    <div data-announcement-branch class="hidden">
                        <label class="panel-label" for="branch_id">Branch</label>
                        <select id="branch_id" name="branch_id" class="panel-select">
                            <option value="">Select branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" data-gym-id="{{ $branch->gym_id }}" @selected((string) old('branch_id') === (string) $branch->id)>
                                    {{ $branch->gym?->name ? $branch->gym->name.' - ' : '' }}{{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <div class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="md:col-span-2 hidden" data-announcement-members>
                        <label class="panel-label" for="member_ids">Members</label>
                        <select id="member_ids" name="member_ids[]" class="panel-select min-h-[220px]" multiple>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected(collect(old('member_ids', []))->contains($member->id))>
                                    {{ $member->name }}{{ $member->email ? ' - '.$member->email : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('member_ids')
                            <div class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>
                        @enderror
                        @error('member_ids.*')
                            <div class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <x-form-input type="datetime-local" name="send_at" label="Send At" :value="old('send_at')" />
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <x-action-button type="submit">Send Announcement</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.announcements.index') }}" variant="secondary">Cancel</x-action-button>
                </div>
            </x-premium-card>

            <div class="space-y-6">
                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Delivery Intelligence</h3>
                    <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <div class="panel-card-muted px-4 py-3">Platform wide reaches every account and should be used sparingly.</div>
                        <div class="panel-card-muted px-4 py-3">Gym wide and branch specific depend on the selected scope and create recipient rows per matched member.</div>
                        <div class="panel-card-muted px-4 py-3">Selected members is best for high-intent operational communication with controlled audience size.</div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Coverage Snapshot</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="panel-card-muted px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Gyms</div>
                            <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $gyms->count() }}</div>
                        </div>
                        <div class="panel-card-muted px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Branches</div>
                            <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $branches->count() }}</div>
                        </div>
                    </div>
                </x-premium-card>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const audienceField = document.querySelector('[data-announcement-audience]');
            const gymBlock = document.querySelector('[data-announcement-gym]');
            const branchBlock = document.querySelector('[data-announcement-branch]');
            const membersBlock = document.querySelector('[data-announcement-members]');
            const gymSelect = document.getElementById('gym_id');
            const branchSelect = document.getElementById('branch_id');

            if (!audienceField || !gymBlock || !branchBlock || !membersBlock || !gymSelect || !branchSelect) {
                return;
            }

            const toggleAudience = () => {
                const value = audienceField.value;
                const needsGym = ['gym_wide', 'branch_specific', 'selected_members', 'offer'].includes(value);
                const needsBranch = value === 'branch_specific';
                const needsMembers = value === 'selected_members';

                gymBlock.classList.toggle('hidden', !needsGym);
                branchBlock.classList.toggle('hidden', !needsBranch);
                membersBlock.classList.toggle('hidden', !needsMembers);
            };

            const filterBranches = () => {
                const gymId = gymSelect.value;

                Array.from(branchSelect.options).forEach((option, index) => {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }

                    option.hidden = gymId !== '' && option.dataset.gymId !== gymId;

                    if (option.hidden && option.selected) {
                        option.selected = false;
                    }
                });
            };

            audienceField.addEventListener('change', toggleAudience);
            gymSelect.addEventListener('change', filterBranches);

            toggleAudience();
            filterBranches();
        });
    </script>
@endpush
