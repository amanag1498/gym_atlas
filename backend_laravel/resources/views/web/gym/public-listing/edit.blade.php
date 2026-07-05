@extends('layouts.panel')

@php
    $galleryPhotos = $gym->gymPhotos->whereNull('branch_id')->where('type', 'gallery')->sortBy('sort_order')->values();
@endphp

@section('content')
    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <div class="space-y-6">
            @if (! $canBePubliclyListed)
                <div class="rounded-3xl border border-amber-400/30 bg-amber-400/10 px-5 py-4 text-sm text-amber-100">
                    <p class="font-semibold text-amber-50">Public listing is restricted</p>
                    <p class="mt-1">This gym is currently {{ ucfirst($gym->status ?: 'inactive') }}. Discovery stays private until the gym is active and approved.</p>
                </div>
            @endif

            <x-premium-card class="p-6">
                <div>
                    <h3 class="panel-section-title">Public listing settings</h3>
                    <p class="panel-section-copy">Control discovery visibility, public pricing, trial requests, and public contact behavior.</p>
                </div>

                <form action="{{ route('web.gym.public-listing.update') }}" method="POST" class="mt-6 space-y-5">
                    @csrf
                    @method('PUT')

                    <label class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-4 text-sm text-slate-200">
                        <input type="hidden" name="public_listing_enabled" value="0">
                        <input type="checkbox" name="public_listing_enabled" value="1" @checked($gym->public_listing_enabled)>
                        <span><span class="font-semibold text-white">Enable public profile</span><br>Allow this gym to appear in discovery once the gym is active and platform-approved.</span>
                    </label>

                    <label class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-4 text-sm text-slate-200">
                        <input type="hidden" name="show_pricing" value="0">
                        <input type="checkbox" name="show_pricing" value="1" @checked($gym->show_pricing ?? $gym->pricing_visible)>
                        <span><span class="font-semibold text-white">Show pricing publicly</span><br>Expose membership plan pricing when the listing is public.</span>
                    </label>

                    <label class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-4 text-sm text-slate-200">
                        <input type="hidden" name="trial_available" value="0">
                        <input type="checkbox" name="trial_available" value="1" @checked($gym->trial_available)>
                        <span><span class="font-semibold text-white">Accept trial requests</span><br>Allow nearby users to request a trial directly from the public profile.</span>
                    </label>

                    <label class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-4 text-sm text-slate-200">
                        <input type="hidden" name="contact_visible" value="0">
                        <input type="checkbox" name="contact_visible" value="1" @checked($gym->contact_visible)>
                        <span><span class="font-semibold text-white">Show public contact action</span><br>Enable the public contact/trial CTA on the public profile preview.</span>
                    </label>

                    <x-action-button type="submit" variant="primary" class="w-full justify-center">Save Public Listing Settings</x-action-button>
                </form>
            </x-premium-card>
        </div>

        <div class="space-y-6">
            <x-premium-card class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="panel-section-title">Public profile preview</h3>
                        <p class="mt-2 text-sm text-slate-400">This is the discovery-facing summary for nearby users.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-status-badge :label="$gym->public_listing_enabled ? 'Public' : 'Private'" />
                        <x-status-badge :label="ucfirst($gym->public_listing_approval_status ?? 'pending')" />
                        <x-status-badge :label="$gym->contact_visible ? 'Contact Visible' : 'Contact Hidden'" />
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-3xl border border-white/10 bg-slate-950/50">
                    @if ($gym->cover_image_url)
                        <img src="{{ $gym->cover_image_thumbnail_url ?: $gym->cover_image_url }}" alt="{{ $gym->name }} cover" class="h-48 w-full object-cover">
                    @else
                        <div class="flex h-48 items-center justify-center text-sm text-slate-400">No cover image configured</div>
                    @endif
                </div>

                <div class="mt-5 flex items-center gap-4">
                    <div class="h-20 w-20 overflow-hidden rounded-2xl border border-white/10 bg-slate-950/50">
                        @if ($gym->logo_url)
                            <img src="{{ $gym->logo_thumbnail_url ?: $gym->logo_url }}" alt="{{ $gym->name }} logo" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-[11px] text-slate-400">No logo</div>
                        @endif
                    </div>
                    <div>
                        <p class="text-2xl font-semibold text-white">{{ $gym->name }}</p>
                        <p class="mt-1 text-sm text-slate-400">{{ $gym->description ?: 'No public description yet.' }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <div class="panel-card-muted px-4 py-3">Address: {{ $gym->address ?: $gym->address_line ?: 'Not configured' }}</div>
                    <div class="panel-card-muted px-4 py-3">Location: {{ $gym->city ?: 'City pending' }}{{ $gym->state ? ' • '.$gym->state : '' }}</div>
                    <div class="panel-card-muted px-4 py-3">Pricing: {{ ($gym->show_pricing ?? $gym->pricing_visible) ? 'Visible on profile' : 'Hidden from public view' }}</div>
                    <div class="panel-card-muted px-4 py-3">Trial CTA: {{ $gym->trial_available ? ($gym->contact_visible ? 'Enabled' : 'Trial on, contact hidden') : 'Disabled' }}</div>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ($gym->facilities as $facility)
                        <span class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs font-medium text-slate-200">{{ $facility->name }}</span>
                    @endforeach
                    @if ($gym->facilities->isEmpty())
                        <span class="text-sm text-slate-500">No facilities added yet.</span>
                    @endif
                </div>

                @if ($galleryPhotos->isNotEmpty())
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        @foreach ($galleryPhotos->take(4) as $photo)
                            <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/50">
                                <img src="{{ $photo->thumbnail_url }}" alt="Gym gallery preview" class="h-28 w-full object-cover">
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-premium-card>
        </div>
    </div>
@endsection
