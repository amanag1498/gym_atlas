@extends('layouts.panel')

@php
    $panelFullWidth = true;
@endphp

@section('content')
    <div class="w-full min-w-0 space-y-6">
        <header class="border-b border-slate-200 pb-5 dark:border-slate-800">
            <a href="{{ route('web.gym.announcements.index', array_merge(request()->only(['gym', 'branch']), ['tab' => 'history'])) }}" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-brand-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i> Announcement history
            </a>
            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-600 dark:text-slate-400">Sent announcement</p>
                    <h2 class="mt-2 break-words text-2xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-3xl">{{ $announcement->title }}</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ optional($announcement->send_at)->format('d M Y, h:i A') ?: 'Send date unavailable' }} · {{ $announcement->creator?->name ?? 'System' }}</p>
                </div>
                <x-status-badge :label="str_replace('_', ' ', ucfirst($announcement->audience_type))" tone="info" />
            </div>
        </header>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Recipients</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums text-slate-950 dark:text-white">{{ $announcement->recipients_count }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Read</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums text-slate-950 dark:text-white">{{ $announcement->read_recipients_count }}</p>
            </div>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(18rem,1fr)]">
            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Message</h3>
                <div class="mt-5 border-t border-slate-200 pt-5 text-base leading-7 whitespace-pre-wrap break-words text-slate-800 dark:border-slate-800 dark:text-slate-200">{{ $announcement->message }}</div>
            </article>

            <aside class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Send details</h3>
                <dl class="mt-4 divide-y divide-slate-200 dark:divide-slate-800">
                    <div class="py-3 first:pt-0">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-400">Gym</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-950 dark:text-slate-100">{{ $announcement->gym?->name ?? 'Not available' }}</dd>
                    </div>
                    <div class="py-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-400">Branch</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-950 dark:text-slate-100">{{ $announcement->branch?->name ?? 'All eligible branches' }}</dd>
                    </div>
                    <div class="py-3 last:pb-0">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-400">Sent by</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-950 dark:text-slate-100">{{ $announcement->creator?->name ?? 'System' }}</dd>
                    </div>
                </dl>
            </aside>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="recipient-heading">
            <div class="flex flex-col gap-2 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-7 dark:border-slate-800">
                <div>
                    <h3 id="recipient-heading" class="text-base font-semibold text-slate-950 dark:text-white">Delivery coverage</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">See which members have opened this announcement.</p>
                </div>
                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $announcement->read_recipients_count }} of {{ $announcement->recipients_count }} read</span>
            </div>
            @forelse ($announcement->recipients as $recipient)
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:px-7 dark:border-slate-800">
                    <div class="min-w-0">
                        <p class="break-words text-sm font-semibold text-slate-950 dark:text-slate-100">{{ $recipient->user?->name ?? 'Member unavailable' }}</p>
                        <p class="mt-1 break-all text-sm text-slate-600 dark:text-slate-400">{{ $recipient->user?->email ?? 'No email' }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 sm:gap-5">
                        <x-status-badge :label="$recipient->read_at ? 'Read' : 'Unread'" :tone="$recipient->read_at ? 'success' : 'warning'" />
                        <time class="text-xs text-slate-600 dark:text-slate-400">{{ optional($recipient->created_at)->format('d M Y, h:i A') ?: 'Date unavailable' }}</time>
                    </div>
                </div>
            @empty
                <div class="p-5 sm:p-7"><x-empty-state title="No recipients" message="No eligible members matched this announcement when it was sent." /></div>
            @endforelse
        </section>
    </div>
@endsection
