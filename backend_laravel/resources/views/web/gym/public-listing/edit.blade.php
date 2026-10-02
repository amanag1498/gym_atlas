@extends('layouts.panel')

@php
    $panelFullWidth = true;
    $galleryPhotos = $gym->gymPhotos->whereNull('branch_id')->where('type', 'gallery')->sortBy('sort_order')->values();
@endphp

@section('content')
    <div class="w-full min-w-0 space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Public listing</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Choose what visitors see when they find {{ $gym->name }}.</p>
            </div>
            <x-action-button as="a" variant="secondary" href="{{ route('web.gym.profile.edit', request()->only(['gym', 'branch'])) }}">Edit gym profile</x-action-button>
        </header>

        @if (! $canBePubliclyListed)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200" role="status">
                <p class="font-semibold">Public listing is currently unavailable</p>
                <p class="mt-1">This gym is {{ ucfirst($gym->status ?: 'inactive') }}. It must be active and approved before it can appear in discovery. You can save the other settings now.</p>
            </div>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.9fr)]">
            <section class="min-w-0 space-y-5" aria-labelledby="listing-settings-heading">
                <div>
                    <h3 id="listing-settings-heading" class="text-lg font-semibold text-slate-950 dark:text-white">Visibility & actions</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Changes to these options take effect when you save.</p>
                </div>
                <form action="{{ route('web.gym.public-listing.update', request()->only(['gym', 'branch'])) }}" method="POST" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @include('web.gym.public-listing._settings-toggles', ['gym' => $gym])
                    <x-action-button type="submit" variant="primary" class="w-full justify-center sm:w-auto">Save listing settings</x-action-button>
                </form>
            </section>

            <aside class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="listing-preview-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h3 id="listing-preview-heading" class="text-base font-semibold text-slate-950 dark:text-white">Current public profile</h3>
                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">Preview of the last saved profile</p>
                    </div>
                    <x-status-badge :label="$gym->public_listing_enabled && $canBePubliclyListed ? 'Public' : 'Private'" />
                </div>

                <div class="relative">
                    @if ($gym->cover_image_url)
                        <img src="{{ $gym->cover_image_thumbnail_url ?: $gym->cover_image_url }}" alt="{{ $gym->name }} cover" class="h-40 w-full object-cover sm:h-48">
                    @else
                        <div class="flex h-40 items-center justify-center bg-slate-100 text-sm text-slate-600 sm:h-48 dark:bg-slate-800 dark:text-slate-400">Add a cover image in Gym Profile</div>
                    @endif
                </div>

                <div class="space-y-5 p-5">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                            @if ($gym->logo_url)
                                <img src="{{ $gym->logo_thumbnail_url ?: $gym->logo_url }}" alt="{{ $gym->name }} logo" class="h-full w-full object-cover">
                            @else
                                <span class="text-xs text-slate-600 dark:text-slate-400">No logo</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h4 class="break-words text-lg font-semibold text-slate-950 dark:text-white">{{ $gym->name }}</h4>
                            <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-400">{{ $gym->city ?: 'City not set' }}{{ $gym->state ? ', '.$gym->state : '' }}</p>
                        </div>
                    </div>

                    <p class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $gym->description ?: 'Add a description in Gym Profile to introduce this gym to visitors.' }}</p>

                    <dl class="divide-y divide-slate-200 border-t border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-800">
                        <div class="flex justify-between gap-4 py-3"><dt class="text-slate-600 dark:text-slate-400">Address</dt><dd class="max-w-[65%] break-words text-right font-medium text-slate-950 dark:text-slate-100">{{ $gym->address ?: $gym->address_line ?: 'Not set' }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-slate-600 dark:text-slate-400">Pricing</dt><dd class="font-medium text-slate-950 dark:text-slate-100">{{ ($gym->show_pricing ?? $gym->pricing_visible) ? 'Shown' : 'Hidden' }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-slate-600 dark:text-slate-400">Trial requests</dt><dd class="font-medium text-slate-950 dark:text-slate-100">{{ $gym->trial_available ? 'Accepted' : 'Off' }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-slate-600 dark:text-slate-400">Contact action</dt><dd class="font-medium text-slate-950 dark:text-slate-100">{{ $gym->contact_visible ? 'Shown' : 'Hidden' }}</dd></div>
                    </dl>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">Facilities</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($gym->facilities as $facility)
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $facility->name }}</span>
                            @empty
                                <span class="text-sm text-slate-600 dark:text-slate-400">No facilities selected yet.</span>
                            @endforelse
                        </div>
                    </div>

                    @if ($galleryPhotos->isNotEmpty())
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($galleryPhotos->take(4) as $photo)
                                <img src="{{ $photo->thumbnail_url }}" alt="Gym gallery photo" class="h-24 w-full rounded-lg object-cover">
                            @endforeach
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
@endsection
