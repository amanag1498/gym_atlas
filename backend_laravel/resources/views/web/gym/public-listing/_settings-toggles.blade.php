@php
    $listingSettings = [
        ['name' => 'public_listing_enabled', 'title' => 'Show this gym in discovery', 'help' => 'Members can find this gym after it is active and approved.', 'checked' => $gym->public_listing_enabled],
        ['name' => 'show_pricing', 'title' => 'Show membership prices', 'help' => 'Display plan prices on the public profile.', 'checked' => $gym->show_pricing ?? $gym->pricing_visible],
        ['name' => 'trial_available', 'title' => 'Accept trial requests', 'help' => 'Let visitors request a trial from the listing.', 'checked' => $gym->trial_available],
        ['name' => 'contact_visible', 'title' => 'Show contact action', 'help' => 'Allow visitors to contact this gym from its profile.', 'checked' => $gym->contact_visible],
    ];
@endphp

<div class="divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
    @foreach ($listingSettings as $setting)
        <label class="flex min-h-20 cursor-pointer items-center justify-between gap-4 px-4 py-4 transition hover:bg-slate-50 sm:px-5 dark:hover:bg-slate-800/70">
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-slate-950 dark:text-slate-100">{{ $setting['title'] }}</span>
                <span class="mt-1 block text-sm leading-5 text-slate-600 dark:text-slate-400">{{ $setting['help'] }}</span>
            </span>
            <span class="shrink-0">
                <input type="hidden" name="{{ $setting['name'] }}" value="0">
                <input type="checkbox" name="{{ $setting['name'] }}" value="1" class="h-5 w-5 cursor-pointer rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800" @checked((bool) old($setting['name'], $setting['checked']))>
            </span>
        </label>
    @endforeach
</div>
