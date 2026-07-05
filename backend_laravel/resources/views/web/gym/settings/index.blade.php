@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">Gym Admin</span>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">Settings</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Attendance, notification preferences, billing notes, and gym operation defaults in one place.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.audit-logs.index', ['gym' => request('gym', $gym->id), 'branch' => request('branch')]) }}">View Audit Logs</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.public-listing.edit', ['gym' => request('gym', $gym->id), 'branch' => request('branch')]) }}">Public Listing</x-action-button>
                </div>
            </div>
        </section>

        <form action="{{ route('web.gym.settings.update', ['gym' => request('gym', $gym->id), 'branch' => request('branch')]) }}" method="POST" class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.85fr)]">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Operations</h3>
                    <p class="panel-section-copy">Duplicate attendance control and the default permission preset automatically applied to newly created staff accounts.</p>

                    <div class="mt-5 space-y-5">
                        <label for="attendance_duplicate_checkin_rule" class="panel-card-muted flex cursor-pointer items-start justify-between gap-4 px-4 py-4">
                            <div>
                                <div class="font-semibold text-slate-950">Prevent duplicate same-day check-ins</div>
                                <p class="mt-1 text-sm text-slate-500">Keeps attendance clean by blocking repeat check-ins for the same member on the same day.</p>
                            </div>
                            <input type="hidden" name="attendance_duplicate_checkin_rule" value="0">
                            <input
                                id="attendance_duplicate_checkin_rule"
                                type="checkbox"
                                name="attendance_duplicate_checkin_rule"
                                value="1"
                                class="mt-1 h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                @checked(old('attendance_duplicate_checkin_rule', $settings['attendance_duplicate_checkin_rule'] ?? false))
                            >
                        </label>

                        <div>
                            <label class="panel-label">Staff permission defaults</label>
                            <div class="grid gap-3 md:grid-cols-2">
                                @foreach ($staffPermissionOptions as $permissionKey => $permissionLabel)
                                    <label class="panel-card-muted flex items-start gap-3 px-4 py-3">
                                        <input
                                            type="checkbox"
                                            name="staff_permission_defaults[]"
                                            value="{{ $permissionKey }}"
                                            class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                            @checked(in_array($permissionKey, old('staff_permission_defaults', $settings['staff_permission_defaults'] ?? []), true))
                                        >
                                        <span>
                                            <span class="block font-semibold text-slate-950">{{ $permissionLabel }}</span>
                                            <span class="text-sm text-slate-500">{{ str($permissionKey)->replace('_', ' ')->title() }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('staff_permission_defaults') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Notification Preferences</h3>
                    <p class="panel-section-copy">These control your own gym-admin alerts. Critical billing or security notifications may still be delivered when required.</p>

                    <div class="mt-5 space-y-3">
                        @foreach ($notificationPreferences as $index => $preference)
                            <div class="panel-card-muted flex items-start justify-between gap-4 px-4 py-4">
                                <div>
                                    <div class="font-semibold text-slate-950">{{ $preference['label'] }}</div>
                                    <div class="mt-1 text-sm text-slate-500">{{ $preference['description'] }}</div>
                                </div>
                                <div>
                                    <input type="hidden" name="notification_preferences[{{ $index }}][notification_type]" value="{{ $preference['notification_type'] }}">
                                    <input type="hidden" name="notification_preferences[{{ $index }}][is_enabled]" value="0">
                                    <input
                                        type="checkbox"
                                        name="notification_preferences[{{ $index }}][is_enabled]"
                                        value="1"
                                        class="mt-1 h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                        @checked(old("notification_preferences.$index.is_enabled", $preference['is_enabled']))
                                    >
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-premium-card>
            </div>

            <div class="space-y-6">
                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Billing Operations Notes</h3>
                    <p class="panel-section-copy">Store internal collection rules, settlement instructions, and branch-specific payment process notes.</p>
                    <textarea name="billing_settings_placeholder" class="panel-textarea mt-4" rows="8" placeholder="Example: Cash collection closes at 9 PM. Bank transfer confirmation reviewed every morning.">{{ old('billing_settings_placeholder', $settings['billing_settings_placeholder'] ?? '') }}</textarea>
                    @error('billing_settings_placeholder') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                </x-premium-card>

                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Shortcuts</h3>
                    <div class="mt-4 space-y-3">
                        <a href="{{ route('web.gym.public-listing.edit', ['gym' => request('gym', $gym->id), 'branch' => request('branch')]) }}" class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3 no-underline transition hover:border-teal-200 hover:bg-white">
                            <span>
                                <span class="block font-semibold text-slate-950">Public profile settings</span>
                                <span class="text-sm text-slate-500">Edit listing visibility, pricing visibility, and contact display.</span>
                            </span>
                            <i class="ti ti-chevron-right text-slate-400"></i>
                        </a>
                        <a href="{{ route('web.gym.branches.index', ['gym' => request('gym', $gym->id), 'branch' => request('branch')]) }}" class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3 no-underline transition hover:border-teal-200 hover:bg-white">
                            <span>
                                <span class="block font-semibold text-slate-950">Branch settings shortcut</span>
                                <span class="text-sm text-slate-500">Manage branch timings, facilities, and active state from the branches tab.</span>
                            </span>
                            <i class="ti ti-chevron-right text-slate-400"></i>
                        </a>
                        <a href="{{ route('web.gym.staff.index', ['gym' => request('gym', $gym->id), 'branch' => request('branch')]) }}" class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3 no-underline transition hover:border-teal-200 hover:bg-white">
                            <span>
                                <span class="block font-semibold text-slate-950">Staff and permissions</span>
                                <span class="text-sm text-slate-500">Review staff roles, branch scope, and permission grants in one place.</span>
                            </span>
                            <i class="ti ti-chevron-right text-slate-400"></i>
                        </a>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Save</h3>
                    <p class="panel-section-copy">Every settings update is written to the gym audit trail.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-action-button type="submit">Save Settings</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.gym.settings.index', ['gym' => request('gym', $gym->id), 'branch' => request('branch')]) }}">Reset</x-action-button>
                    </div>
                </x-premium-card>
            </div>
        </form>
    </div>
@endsection
