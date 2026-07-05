@extends('layouts.panel')

@php
    use App\Support\Scheduling\OperatingHours;

    $gymSchedule = OperatingHours::normalize($gym->timings ?? [], $gym->weekly_off ?? []);
    $approvalStatus = $gym->approval_status ?: $gym->status ?: 'pending';
    $branchScheduleSummary = static fn ($branch) => collect(OperatingHours::DAYS)
        ->map(fn ($day) => OperatingHours::dayLabel($day).': '.OperatingHours::formatDaySlots(OperatingHours::normalize($branch->timings ?? [], $branch->weekly_off ?? [])[$day] ?? []))
        ->implode(' | ');
@endphp

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.gyms.edit', $gym) }}">Edit Gym</x-action-button>
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.create', ['gym' => $gym->id]) }}">Billing</x-action-button>
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.index') }}">Back to Gyms</x-action-button>
    @endsection

    <div class="space-y-6">
        @if (session('owner_temp_password'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                Temporary owner password: <code>{{ session('owner_temp_password') }}</code>
            </div>
        @endif

        <section class="panel-hero">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    @if ($gym->logo_url)
                        <img src="{{ $gym->logo_url }}" alt="{{ $gym->name }}" class="h-20 w-20 rounded-[1.4rem] border border-white/40 object-cover shadow-lg shadow-slate-950/10 dark:border-slate-700">
                    @else
                        <div class="flex h-20 w-20 items-center justify-center rounded-[1.4rem] border border-white/40 bg-brand-500/10 text-2xl font-semibold text-brand-600 shadow-lg shadow-slate-950/10 dark:border-slate-700 dark:text-brand-300">
                            {{ strtoupper(substr($gym->name, 0, 1)) }}
                        </div>
                    @endif

                    <div class="min-w-0">
                        <div class="flex flex-wrap gap-2">
                            <x-status-badge :label="$approvalStatus" />
                            <x-status-badge :label="$gym->is_active ? 'Active' : 'Inactive'" :tone="$gym->is_active ? 'success' : 'danger'" />
                            <x-status-badge :label="$gym->is_verified ? 'Verified' : 'Unverified'" :tone="$gym->is_verified ? 'verified' : 'neutral'" />
                            <x-status-badge :label="$gym->public_listing_enabled ? 'Public' : 'Private'" />
                        </div>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $gym->name }}</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $gym->description ?: 'No description has been added for this gym yet.' }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($gym->is_featured)
                                <x-status-badge label="Featured" tone="featured" />
                            @endif
                            @if ($gym->is_promoted)
                                <x-status-badge label="Promoted" tone="promoted" />
                            @endif
                            <x-status-badge :label="$gym->owner?->name ? 'Owner linked' : 'Owner missing'" :tone="$gym->owner?->name ? 'success' : 'warning'" />
                        </div>
                    </div>
                </div>

                <div class="grid w-full gap-3 sm:grid-cols-2 xl:w-[360px]">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Location</div>
                        <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $gym->city ?: 'N/A' }}{{ $gym->state ? ', '.$gym->state : '' }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym->pincode ?: 'Pincode not added' }}</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Listing</div>
                        <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ ucfirst($gym->public_listing_approval_status ?? 'pending') }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym->show_pricing ? 'Pricing visible' : 'Pricing hidden' }}</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <x-stat-card label="Branches" :value="$gym->branches_count" hint="Locations" tone="sky" />
            <x-stat-card label="Members" :value="$gym->member_profiles_count" hint="Linked members" tone="emerald" />
            <x-stat-card label="Trainers" :value="$gym->trainer_profiles_count" hint="Assigned trainers" tone="violet" />
            <x-stat-card label="Plans" :value="$gym->membership_plans_count" hint="Member plans" tone="amber" />
            <x-stat-card label="Trials" :value="$gym->trial_requests_count" hint="Trial leads" tone="rose" />
            <x-stat-card label="Payments" :value="$gym->payments_count" hint="Recorded payments" tone="sky" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(340px,0.8fr)]">
            <div class="space-y-6">
                <x-premium-card class="overflow-hidden">
                    <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                        <h3 class="panel-section-title">Profile and Operations</h3>
                        <p class="panel-section-copy">Address, ownership, facilities, and weekly operating coverage.</p>
                    </div>
                    <div class="grid gap-5 p-5 md:grid-cols-2">
                        <div class="space-y-3">
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Address</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $gym->address_line ?: $gym->address ?: 'Not added' }}</div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">City / State / Pincode</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $gym->city ?: 'N/A' }}{{ $gym->state ? ', '.$gym->state : '' }}{{ $gym->pincode ? ' • '.$gym->pincode : '' }}</div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Coordinates</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $gym->latitude ?: '—' }} / {{ $gym->longitude ?: '—' }}</div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Owner</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $gym->owner?->name ?? 'Unassigned' }}</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym->owner?->email ?? 'No owner email' }}</div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Contact Channels</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $gym->contact_number ?: 'No public number' }}</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym->instagram_profile ?: 'No Instagram profile' }}</div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Discovery Controls</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-status-badge :label="$gym->public_listing_enabled ? 'Public listing' : 'Hidden listing'" />
                                    <x-status-badge :label="$gym->show_pricing ? 'Pricing visible' : 'Pricing hidden'" />
                                    <x-status-badge :label="$gym->trial_available ? 'Trial enabled' : 'No trial'" />
                                    <x-status-badge :label="$gym->contact_visible ? 'Contact visible' : 'Contact hidden'" />
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Facilities</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse ($gym->facilities as $facility)
                                        <x-status-badge :label="$facility->name" tone="info" />
                                    @empty
                                        <x-status-badge label="No facilities" tone="neutral" />
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="panel-card-muted px-4 py-4 md:col-span-2">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Weekly Schedule</div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach (OperatingHours::DAYS as $day)
                                    <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ OperatingHours::dayLabel($day) }}</div>
                                        <div class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ OperatingHours::formatDaySlots($gymSchedule[$day] ?? []) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-premium-card>

                <x-table-wrapper class="overflow-hidden">
                    <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h3 class="panel-section-title">Branch Directory</h3>
                                <p class="panel-section-copy">Coverage, facilities, and operating state across every branch.</p>
                            </div>
                            @if ($primaryBranch)
                                <x-status-badge :label="'Primary: '.$primaryBranch->name" tone="verified" />
                            @endif
                        </div>
                    </div>

                    @if ($gym->branches->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="panel-table min-w-[1100px]">
                                <thead>
                                    <tr>
                                        <th>Branch</th>
                                        <th>Address</th>
                                        <th>City</th>
                                        <th>Schedule</th>
                                        <th>Facilities</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($gym->branches as $branch)
                                        <tr>
                                            <td>
                                                <div class="font-semibold text-slate-950 dark:text-white">{{ $branch->name }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $branch->slug }}</div>
                                            </td>
                                            <td>{{ $branch->address_line ?: $branch->address ?: 'N/A' }}</td>
                                            <td>{{ $branch->city ?: 'N/A' }}</td>
                                            <td class="max-w-[340px]">
                                                <div class="text-xs leading-5 text-slate-600 dark:text-slate-300">{{ $branchScheduleSummary($branch) }}</div>
                                            </td>
                                            <td>{{ $branch->facilities->pluck('name')->implode(', ') ?: 'N/A' }}</td>
                                            <td><x-status-badge :label="$branch->is_active ? 'Active' : 'Inactive'" :tone="$branch->is_active ? 'success' : 'danger'" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-5 py-6">
                            <x-empty-state title="No branches found" message="This gym has not created any branches yet." />
                        </div>
                    @endif
                </x-table-wrapper>
            </div>

            <div class="space-y-6">
                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Platform Controls</h3>
                    <p class="panel-section-copy">Review, activation, verification, and discovery promotion actions.</p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-status-badge :label="'Approval: '.ucfirst($approvalStatus)" />
                        @if ($approvalStatus === 'approved')
                            <x-status-badge label="Already approved" tone="success" />
                        @elseif ($approvalStatus === 'rejected')
                            <x-status-badge label="Already rejected" tone="danger" />
                        @else
                            <x-status-badge label="Awaiting review" tone="warning" />
                        @endif
                    </div>

                    <div class="mt-4 space-y-3">
                        @if ($approvalStatus !== 'approved')
                            <form action="{{ route('web.admin.gyms.approve', $gym) }}" method="POST" onsubmit="return confirm('Approve this gym?');">
                                @csrf
                                <x-action-button type="submit" class="w-full">Approve Gym</x-action-button>
                            </form>
                        @endif

                        @if ($approvalStatus !== 'rejected')
                            <form action="{{ route('web.admin.gyms.reject', $gym) }}" method="POST" onsubmit="return confirm('Reject this gym?');" class="space-y-2">
                                @csrf
                                <textarea name="approval_notes" class="panel-textarea min-h-24" placeholder="Reason for rejection"></textarea>
                                <x-action-button type="submit" variant="danger" class="w-full">Reject Gym</x-action-button>
                            </form>
                        @endif

                        <form action="{{ $gym->is_active ? route('web.admin.gyms.deactivate', $gym) : route('web.admin.gyms.activate', $gym) }}" method="POST" onsubmit="return confirm('{{ $gym->is_active ? 'Deactivate' : 'Activate' }} this gym?');">
                            @csrf
                            <x-action-button type="submit" :variant="$gym->is_active ? 'danger' : 'secondary'" class="w-full">{{ $gym->is_active ? 'Deactivate Gym' : 'Activate Gym' }}</x-action-button>
                        </form>

                        <form action="{{ route('web.admin.gyms.verify', $gym) }}" method="POST" onsubmit="return confirm('{{ $gym->is_verified ? 'Unverify' : 'Verify' }} this gym?');">
                            @csrf
                            <x-action-button type="submit" variant="secondary" class="w-full">{{ $gym->is_verified ? 'Unverify Gym' : 'Verify Gym' }}</x-action-button>
                        </form>

                        <form action="{{ route('web.admin.gyms.feature', $gym) }}" method="POST">
                            @csrf
                            <x-action-button type="submit" variant="secondary" class="w-full">{{ $gym->is_featured ? 'Unfeature Gym' : 'Feature Gym' }}</x-action-button>
                        </form>

                        <form action="{{ route('web.admin.gyms.promote', $gym) }}" method="POST">
                            @csrf
                            <x-action-button type="submit" variant="secondary" class="w-full">{{ $gym->is_promoted ? 'Unpromote Gym' : 'Promote Gym' }}</x-action-button>
                        </form>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="panel-section-title">Platform Billing</h3>
                            <p class="panel-section-copy">What this gym pays to the platform for admin services.</p>
                        </div>
                        <x-action-button as="a" href="{{ route('web.admin.gym-platform-subscriptions.create', ['gym' => $gym->id]) }}">Assign</x-action-button>
                    </div>

                    @if ($gym->currentPlatformSubscription)
                        <div class="mt-4 space-y-3">
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Plan</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $gym->currentPlatformSubscription->plan?->name ?? ($gym->currentPlatformSubscription->plan_snapshot['name'] ?? 'Custom billing') }}</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym->currentPlatformSubscription->plan_snapshot['cadence_label'] ?? $gym->currentPlatformSubscription->plan?->cadence_label ?? 'Custom cadence' }}</div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <x-status-badge :label="$gym->currentPlatformSubscription->status" />
                                    <x-status-badge :label="$gym->currentPlatformSubscription->auto_renew ? 'Auto Renew' : 'Manual Renew'" tone="verified" />
                                </div>
                                <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    Starts {{ optional($gym->currentPlatformSubscription->starts_at)->format('d M Y') ?? 'n/a' }}
                                    @if ($gym->currentPlatformSubscription->renews_at)
                                        • Renews {{ $gym->currentPlatformSubscription->renews_at->format('d M Y') }}
                                    @endif
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Charges</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) $gym->currentPlatformSubscription->billing_amount, 0) }}</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Setup ₹{{ number_format((float) $gym->currentPlatformSubscription->setup_fee_amount, 0) }}</div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Included Services</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse ($gym->currentPlatformSubscription->included_services ?? [] as $service)
                                        <x-status-badge :label="$service" tone="info" />
                                    @empty
                                        <x-status-badge label="No services captured" tone="neutral" />
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Recent Platform Invoices</div>
                                        <div class="mt-2 space-y-2">
                                            @forelse ($gym->currentPlatformSubscription->invoices->sortByDesc('issued_at')->take(3) as $invoice)
                                                <div class="flex items-center justify-between gap-3 text-sm">
                                                    <div>
                                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $invoice->invoice_number }}</div>
                                                        <div class="text-xs text-slate-500 dark:text-slate-400">₹{{ number_format((float) $invoice->total_amount, 0) }} • Due {{ optional($invoice->due_at)->format('d M Y') ?: 'n/a' }}</div>
                                                    </div>
                                                    <x-status-badge :label="$invoice->status" />
                                                </div>
                                            @empty
                                                <x-status-badge label="No invoices yet" tone="neutral" />
                                            @endforelse
                                        </div>
                                    </div>
                                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.ledger', $gym->currentPlatformSubscription) }}">Ledger</x-action-button>
                                </div>
                            </div>
                            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.edit', $gym->currentPlatformSubscription) }}">Manage Billing</x-action-button>
                        </div>
                    @else
                        <div class="mt-4">
                            <x-empty-state title="No platform billing assigned" message="Attach a platform plan or custom subscription so the gym's SaaS billing is managed from admin." :action-href="route('web.admin.gym-platform-subscriptions.create', ['gym' => $gym->id])" action-label="Assign Billing" />
                        </div>
                    @endif
                </x-premium-card>

                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Public Listing Settings</h3>
                    <div class="mt-4 space-y-3">
                        <div class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Listing Enabled</span>
                            <x-status-badge :label="$gym->public_listing_enabled ? 'Enabled' : 'Disabled'" />
                        </div>
                        <div class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Listing Approval</span>
                            <x-status-badge :label="ucfirst($gym->public_listing_approval_status ?? 'pending')" />
                        </div>
                        <div class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Pricing Visibility</span>
                            <x-status-badge :label="$gym->show_pricing ? 'Visible' : 'Hidden'" />
                        </div>
                        <div class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Trial Availability</span>
                            <x-status-badge :label="$gym->trial_available ? 'Trial' : 'No Trial'" />
                        </div>
                        <div class="panel-card-muted flex items-center justify-between gap-3 px-4 py-3">
                            <span class="text-sm text-slate-600 dark:text-slate-300">Contact Visibility</span>
                            <x-status-badge :label="$gym->contact_visible ? 'Visible' : 'Hidden'" />
                        </div>
                    </div>
                </x-premium-card>

                @if ($gym->cover_image_url)
                    <x-premium-card class="overflow-hidden">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <h3 class="panel-section-title">Cover Image</h3>
                        </div>
                        <div class="p-5">
                            <img src="{{ $gym->cover_image_url }}" alt="{{ $gym->name }} cover" class="w-full rounded-[1.35rem] border border-slate-200/80 object-cover dark:border-slate-800">
                        </div>
                    </x-premium-card>
                @endif
            </div>
        </div>
    </div>
@endsection
