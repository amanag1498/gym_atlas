@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Announcement detail</p>
                    <h3 class="mt-3 text-3xl font-semibold tracking-tight text-white">{{ $announcement->title }}</h3>
                    <p class="mt-3 max-w-2xl text-sm text-slate-300">Review message scope, audience, and sender details for this announcement.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-status-badge :label="str_replace('_', ' ', ucfirst($announcement->audience_type))" tone="info" />
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.announcements.index', request()->only(['gym', 'branch'])) }}">Back</x-action-button>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Message</h3>
                <div class="mt-6 panel-card-muted p-5 text-sm leading-7 text-slate-200 whitespace-pre-wrap">{{ $announcement->message }}</div>
            </x-premium-card>

            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Delivery metadata</h3>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Gym</p>
                        <p class="mt-2 text-sm text-white">{{ $announcement->gym?->name ?? 'N/A' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Branch</p>
                        <p class="mt-2 text-sm text-white">{{ $announcement->branch?->name ?? 'All applicable branches' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Created by</p>
                        <p class="mt-2 text-sm text-white">{{ $announcement->creator?->name ?? 'System' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Sent at</p>
                        <p class="mt-2 text-sm text-white">{{ optional($announcement->send_at)->format('d M Y H:i') }}</p>
                    </div>
                </div>
            </x-premium-card>
        </div>
    </div>
@endsection
