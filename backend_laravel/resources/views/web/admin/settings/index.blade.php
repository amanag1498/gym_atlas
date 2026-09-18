@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Platform Control</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Settings</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Support details, legal links, monetization controls, and shortcuts to live platform management.</p>
                </div>
                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.audit-logs.index') }}">View Audit Logs</x-action-button>
            </div>
        </section>

        @if ($settingsCount === 0)
            <x-premium-card class="p-5">
                <div class="flex items-start gap-4">
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                        <i class="ti ti-alert-circle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="panel-section-title">No Stored Platform Settings Yet</h3>
                        <p class="panel-section-copy">The `platform_settings` table is currently empty. The first save will create the initial configuration snapshot used by support, legal, and monetization surfaces.</p>
                    </div>
                </div>
            </x-premium-card>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.85fr)]">
            <x-premium-card class="p-6">
                <div>
                    <h3 class="panel-section-title">Platform settings</h3>
                    <p class="panel-section-copy">All values are stored centrally in `platform_settings` and audited on save.</p>
                </div>

                <form action="{{ route('web.admin.settings.update') }}" method="POST" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    @csrf
                    @method('PUT')

                    <div class="xl:col-span-3">
                        <label class="panel-label" for="support_email">Support Email</label>
                        <input id="support_email" name="support_email" value="{{ old('support_email', $settings['support_email'] ?? '') }}" class="panel-input">
                        @error('support_email') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="xl:col-span-3">
                        <label class="panel-label" for="support_phone">Support Phone</label>
                        <input id="support_phone" name="support_phone" value="{{ old('support_phone', $settings['support_phone'] ?? '') }}" class="panel-input">
                        @error('support_phone') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="xl:col-span-3">
                        <label class="panel-label" for="privacy_policy_url">Privacy Policy URL</label>
                        <input id="privacy_policy_url" name="privacy_policy_url" value="{{ old('privacy_policy_url', $settings['privacy_policy_url'] ?? '') }}" class="panel-input">
                        @error('privacy_policy_url') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="xl:col-span-3">
                        <label class="panel-label" for="terms_url">Terms URL</label>
                        <input id="terms_url" name="terms_url" value="{{ old('terms_url', $settings['terms_url'] ?? '') }}" class="panel-input">
                        @error('terms_url') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2 xl:col-span-2">
                        <label class="panel-label" for="default_commission_percentage">Default Commission %</label>
                        <input id="default_commission_percentage" name="default_commission_percentage" value="{{ old('default_commission_percentage', $settings['default_commission_percentage'] ?? '') }}" class="panel-input">
                        @error('default_commission_percentage') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    <label class="md:col-span-2 xl:col-span-6 panel-card-muted flex items-start justify-between gap-4 px-4 py-4">
                        <span><span class="block font-semibold text-slate-950">Enable transactional email globally</span><span class="mt-1 block text-sm text-slate-500">Master switch for invitations, trial updates, receipts, and automated reminders. Gym-level switches can further disable delivery.</span></span>
                        <span><input type="hidden" name="transactional_email_enabled" value="0"><input type="checkbox" name="transactional_email_enabled" value="1" class="mt-1 h-5 w-5 rounded border-slate-300 text-teal-600" @checked(old('transactional_email_enabled', $settings['transactional_email_enabled'] ?? true))></span>
                    </label>

                    <label class="md:col-span-2 xl:col-span-6 panel-card-muted flex items-start justify-between gap-4 px-4 py-4">
                        <span><span class="block font-semibold text-slate-950">Enable hidden demo app login</span><span class="mt-1 block text-sm text-slate-500">Allows the configured reviewer accounts to sign in from the triple-tap Atlas logo flow in the Member and Trainer apps.</span></span>
                        <span><input type="hidden" name="demo_login_enabled" value="0"><input type="checkbox" name="demo_login_enabled" value="1" class="mt-1 h-5 w-5 rounded border-slate-300 text-teal-600" @checked(old('demo_login_enabled', $settings['demo_login_enabled'] ?? false))></span>
                    </label>

                    <div class="xl:col-span-3">
                        <label class="panel-label" for="demo_member_login_email">Member App Demo Email</label>
                        <input id="demo_member_login_email" name="demo_member_login_email" value="{{ old('demo_member_login_email', $settings['demo_member_login_email'] ?? '') }}" class="panel-input" placeholder="reviewer-member@example.com">
                        <p class="mt-2 text-xs text-slate-500">Must match an existing active member-role user.</p>
                        @error('demo_member_login_email') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="xl:col-span-3">
                        <label class="panel-label" for="demo_trainer_login_email">Trainer App Demo Email</label>
                        <input id="demo_trainer_login_email" name="demo_trainer_login_email" value="{{ old('demo_trainer_login_email', $settings['demo_trainer_login_email'] ?? '') }}" class="panel-input" placeholder="reviewer-trainer@example.com">
                        <p class="mt-2 text-xs text-slate-500">Must match an existing active trainer-role user.</p>
                        @error('demo_trainer_login_email') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2 xl:col-span-6 mt-2 border-t border-slate-200 pt-6 dark:border-slate-700">
                        <h4 class="text-base font-semibold text-slate-950 dark:text-white">App availability</h4>
                        <p class="mt-1 text-sm text-slate-500">Block both mobile apps during maintenance or require a minimum Member/Trainer build. App config and platform administration remain available.</p>
                        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">Publish and verify the replacement build first. A store URL is required whenever a minimum build above 1 is enforced.</p>
                    </div>

                    <label class="md:col-span-2 xl:col-span-3 panel-card-muted flex items-start justify-between gap-4 px-4 py-4">
                        <span><span class="block font-semibold text-slate-950">Maintenance mode</span><span class="mt-1 block text-sm text-slate-500">Shows a blocking service-status screen in both apps.</span></span>
                        <span><input type="hidden" name="maintenance_mode_enabled" value="0"><input type="checkbox" name="maintenance_mode_enabled" value="1" class="mt-1 h-5 w-5 rounded border-slate-300 text-teal-600" @checked(old('maintenance_mode_enabled', $settings['maintenance_mode_enabled'] ?? false))></span>
                    </label>

                    <label class="md:col-span-2 xl:col-span-3 panel-card-muted flex items-start justify-between gap-4 px-4 py-4">
                        <span><span class="block font-semibold text-slate-950">Force app upgrade</span><span class="mt-1 block text-sm text-slate-500">Rejects builds below the configured minimum with HTTP 426.</span></span>
                        <span><input type="hidden" name="force_upgrade_enabled" value="0"><input type="checkbox" name="force_upgrade_enabled" value="1" class="mt-1 h-5 w-5 rounded border-slate-300 text-teal-600" @checked(old('force_upgrade_enabled', $settings['force_upgrade_enabled'] ?? false))></span>
                    </label>

                    <div class="xl:col-span-3">
                        <label class="panel-label" for="maintenance_title">Maintenance title</label>
                        <input id="maintenance_title" name="maintenance_title" value="{{ old('maintenance_title', $settings['maintenance_title'] ?? '') }}" class="panel-input">
                        @error('maintenance_title') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>
                    <div class="xl:col-span-3">
                        <label class="panel-label" for="maintenance_message">Maintenance message</label>
                        <textarea id="maintenance_message" name="maintenance_message" rows="2" class="panel-input">{{ old('maintenance_message', $settings['maintenance_message'] ?? '') }}</textarea>
                        @error('maintenance_message') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>
                    <div class="xl:col-span-3">
                        <label class="panel-label" for="upgrade_title">Upgrade title</label>
                        <input id="upgrade_title" name="upgrade_title" value="{{ old('upgrade_title', $settings['upgrade_title'] ?? '') }}" class="panel-input">
                        @error('upgrade_title') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>
                    <div class="xl:col-span-3">
                        <label class="panel-label" for="upgrade_message">Upgrade message</label>
                        <textarea id="upgrade_message" name="upgrade_message" rows="2" class="panel-input">{{ old('upgrade_message', $settings['upgrade_message'] ?? '') }}</textarea>
                        @error('upgrade_message') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    @foreach (['member' => 'Member', 'trainer' => 'Trainer'] as $appKey => $appLabel)
                        @foreach (['android' => 'Android', 'ios' => 'iOS'] as $platformKey => $platformLabel)
                            <div class="md:col-span-2 xl:col-span-6 mt-2">
                                <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $appLabel }} · {{ $platformLabel }}</div>
                            </div>
                            <div class="xl:col-span-1">
                                <label class="panel-label" for="{{ $appKey }}_{{ $platformKey }}_min_build">Minimum build</label>
                                <input id="{{ $appKey }}_{{ $platformKey }}_min_build" name="{{ $appKey }}_{{ $platformKey }}_min_build" type="number" min="1" value="{{ old("{$appKey}_{$platformKey}_min_build", $settings["{$appKey}_{$platformKey}_min_build"] ?? 1) }}" class="panel-input">
                                @error($appKey.'_'.$platformKey.'_min_build') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                            </div>
                            <div class="xl:col-span-1">
                                <label class="panel-label" for="{{ $appKey }}_{{ $platformKey }}_min_version">Version label</label>
                                <input id="{{ $appKey }}_{{ $platformKey }}_min_version" name="{{ $appKey }}_{{ $platformKey }}_min_version" value="{{ old("{$appKey}_{$platformKey}_min_version", $settings["{$appKey}_{$platformKey}_min_version"] ?? '1.0.0') }}" class="panel-input">
                                @error($appKey.'_'.$platformKey.'_min_version') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                            </div>
                            <div class="xl:col-span-4">
                                <label class="panel-label" for="{{ $appKey }}_{{ $platformKey }}_store_url">Store URL</label>
                                <input id="{{ $appKey }}_{{ $platformKey }}_store_url" name="{{ $appKey }}_{{ $platformKey }}_store_url" value="{{ old("{$appKey}_{$platformKey}_store_url", $settings["{$appKey}_{$platformKey}_store_url"] ?? '') }}" class="panel-input" placeholder="https://...">
                                @error($appKey.'_'.$platformKey.'_store_url') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                            </div>
                        @endforeach
                    @endforeach

                    <div class="md:col-span-2 xl:col-span-2">
                        <label class="panel-label" for="promoted_listing_price">Promoted Listing Price</label>
                        <input id="promoted_listing_price" name="promoted_listing_price" value="{{ old('promoted_listing_price', $settings['promoted_listing_price'] ?? '') }}" class="panel-input">
                        @error('promoted_listing_price') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2 xl:col-span-2">
                        <label class="panel-label" for="featured_listing_price">Featured Listing Price</label>
                        <input id="featured_listing_price" name="featured_listing_price" value="{{ old('featured_listing_price', $settings['featured_listing_price'] ?? '') }}" class="panel-input">
                        @error('featured_listing_price') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2 xl:col-span-6 flex flex-wrap gap-2">
                        <x-action-button type="submit">Save Settings</x-action-button>
                        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.settings.index') }}">Reset</x-action-button>
                    </div>
                </form>
            </x-premium-card>

            <div class="space-y-6">
                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Recent email delivery</h3>
                    <div class="mt-4 space-y-2 text-sm">@forelse($recentEmailDeliveries as $delivery)<div class="panel-card-muted px-3 py-2"><div class="font-semibold text-slate-950">{{ $delivery->subject }}</div><div class="text-slate-500">{{ $delivery->recipient_email }} · {{ $delivery->gym?->name ?? 'Platform' }} · {{ $delivery->status }}</div></div>@empty<div class="text-slate-500">No transactional email has been recorded yet.</div>@endforelse</div>
                </x-premium-card>
                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Support preview</h3>
                    <div class="mt-4 space-y-3">
                        <div class="panel-card-muted px-4 py-3">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Support email</span>
                            <p class="mt-1 font-medium text-slate-950 dark:text-white">{{ $settings['support_email'] ?? 'Not configured' }}</p>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Support phone</span>
                            <p class="mt-1 font-medium text-slate-950 dark:text-white">{{ $settings['support_phone'] ?? 'Not configured' }}</p>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Privacy URL</span>
                            <p class="mt-1 break-all font-medium text-slate-950 dark:text-white">{{ $settings['privacy_policy_url'] ?? 'Not configured' }}</p>
                        </div>
                        <div class="panel-card-muted px-4 py-3">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Terms URL</span>
                            <p class="mt-1 break-all font-medium text-slate-950 dark:text-white">{{ $settings['terms_url'] ?? 'Not configured' }}</p>
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Monetization snapshot</h3>
                    <div class="mt-4 space-y-3">
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Default commission</span>
                            <span class="font-semibold text-slate-950 dark:text-white">{{ $settings['default_commission_percentage'] ?? '0' }}%</span>
                        </div>
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Promoted listing price</span>
                            <span class="font-semibold text-slate-950 dark:text-white">{{ $settings['promoted_listing_price'] ?? '0' }}</span>
                        </div>
                        <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Featured listing price</span>
                            <span class="font-semibold text-slate-950 dark:text-white">{{ $settings['featured_listing_price'] ?? '0' }}</span>
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Gym subscription billing</h3>
                    <div class="mt-4 space-y-3">
                        <a href="{{ route('web.admin.platform-subscription-plans.index') }}" class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3 no-underline transition hover:border-brand-200 hover:bg-white dark:hover:bg-white/[0.04]">
                            <span>
                                <span class="block font-semibold text-slate-950 dark:text-white">Platform plans</span>
                                <span class="text-sm text-slate-500 dark:text-slate-400">Define what gyms pay the platform and which services are included.</span>
                            </span>
                            <i class="ti ti-chevron-right text-slate-400"></i>
                        </a>
                        <a href="{{ route('web.admin.gym-platform-subscriptions.index') }}" class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3 no-underline transition hover:border-brand-200 hover:bg-white dark:hover:bg-white/[0.04]">
                            <span>
                                <span class="block font-semibold text-slate-950 dark:text-white">Gym billing</span>
                                <span class="text-sm text-slate-500 dark:text-slate-400">Assign platform plans to gyms, override pricing, and track renewal state.</span>
                            </span>
                            <i class="ti ti-chevron-right text-slate-400"></i>
                        </a>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Live management</h3>
                    <div class="mt-4 space-y-3">
                        <a href="{{ route('web.admin.banners.index') }}" class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3 no-underline transition hover:border-teal-200 hover:bg-white dark:hover:bg-white/[0.04]">
                            <span>
                                <span class="block font-semibold text-slate-950 dark:text-white">App banners</span>
                                <span class="text-sm text-slate-500 dark:text-slate-400">Create, pause, reorder, and remove promotional banners.</span>
                            </span>
                            <i class="ti ti-chevron-right text-slate-400"></i>
                        </a>
                        <a href="{{ route('web.admin.announcements.index') }}" class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3 no-underline transition hover:border-teal-200 hover:bg-white dark:hover:bg-white/[0.04]">
                            <span>
                                <span class="block font-semibold text-slate-950 dark:text-white">Platform announcements</span>
                                <span class="text-sm text-slate-500 dark:text-slate-400">Send platform-wide notifications to app users.</span>
                            </span>
                            <i class="ti ti-chevron-right text-slate-400"></i>
                        </a>
                        <a href="{{ route('web.admin.exercises.index') }}" class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3 no-underline transition hover:border-teal-200 hover:bg-white dark:hover:bg-white/[0.04]">
                            <span>
                                <span class="block font-semibold text-slate-950 dark:text-white">Exercise book</span>
                                <span class="text-sm text-slate-500 dark:text-slate-400">Maintain the global exercise library used by workouts.</span>
                            </span>
                            <i class="ti ti-chevron-right text-slate-400"></i>
                        </a>
                    </div>
                </x-premium-card>
            </div>
        </div>
    </div>
@endsection
