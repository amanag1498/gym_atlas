@extends('layouts.panel')

@section('content')
    @section('page_actions')
        @if ($userDetail->hasRole(\App\Enums\RoleName::GymOwner->value))
            <x-action-button as="a" href="{{ route('web.admin.gym-owners.show', $userDetail) }}">Owner Detail</x-action-button>
        @endif
        <x-action-button as="a" variant="secondary" href="{{ route('web.admin.users.index') }}">Back to Users</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap gap-2">
                        <x-status-badge :label="$userDetail->is_active ? 'Active' : 'Inactive'" :tone="$userDetail->is_active ? 'success' : 'danger'" />
                        <x-status-badge :label="str($userDetail->active_role ?: 'none')->replace('_', ' ')->title()" tone="info" />
                        @foreach ($userDetail->roles as $role)
                            <x-status-badge :label="str($role->name)->replace('_', ' ')->title()" tone="neutral" />
                        @endforeach
                    </div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $userDetail->name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $userDetail->email }}</p>
                    @if ($hasPhoneColumn && $userDetail->phone)
                        <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $userDetail->phone }}</p>
                    @endif
                </div>

                <div class="grid w-full gap-3 sm:grid-cols-2 xl:w-[360px]">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Created</div>
                        <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $userDetail->created_at?->format('d M Y') ?? 'N/A' }}</div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Last Login</div>
                        <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $userDetail->last_login_at?->format('d M Y h:i A') ?? 'Never' }}</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="Roles" :value="$userDetail->roles->count()" hint="Assigned roles" tone="sky" />
            <x-stat-card label="Owned Gyms" :value="$userDetail->owned_gyms_count" hint="Owner footprint" tone="violet" />
            <x-stat-card label="Assigned Members" :value="$userDetail->assigned_members_count" hint="Trainer load" tone="amber" />
            <x-stat-card label="Memberships" :value="$userDetail->member_memberships_count" hint="Member history" tone="emerald" />
            <x-stat-card label="Devices" :value="$userDetail->fcm_tokens_count" hint="Push tokens" tone="rose" />
        </div>

        <div class="space-y-6">
            <div class="space-y-6">
                <x-premium-card class="overflow-hidden">
                    <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                        <h3 class="panel-section-title">Account Context</h3>
                        <p class="panel-section-copy">Role mappings, linked gyms, and profile-level operational context.</p>
                    </div>
                    <div class="admin-detail-grid p-5">
                        <div class="panel-card-muted px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Linked Gyms</div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($userDetail->gyms as $gym)
                                    <x-status-badge :label="$gym->name" tone="info" />
                                @empty
                                    <x-status-badge label="No direct gyms" tone="neutral" />
                                @endforelse
                            </div>
                        </div>

                        <div class="panel-card-muted px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Linked Branches</div>
                            <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $userDetail->branches->count() }}</div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $userDetail->branches->pluck('name')->implode(', ') ?: 'No branches linked' }}</div>
                        </div>

                        @if ($userDetail->managedTrainerProfile)
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Trainer Profile</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $userDetail->managedTrainerProfile->gym?->name ?? 'No gym linked' }}</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $userDetail->managedTrainerProfile->branch?->name ?? 'No branch linked' }}</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <x-status-badge :label="$userDetail->managedTrainerProfile->verification_status ?: 'pending'" tone="warning" />
                                    <x-status-badge :label="$userDetail->managedTrainerProfile->is_active ? 'Profile active' : 'Profile inactive'" :tone="$userDetail->managedTrainerProfile->is_active ? 'success' : 'danger'" />
                                </div>
                            </div>
                        @endif

                        @if ($userDetail->memberProfile)
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Member Profile</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $userDetail->memberProfile->gym?->name ?? 'No gym linked' }}</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $userDetail->memberProfile->branch?->name ?? 'No branch linked' }}</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <x-status-badge :label="$userDetail->memberProfile->membership_status ?: 'inactive'" />
                                    @if ($userDetail->memberProfile->membership_expires_on)
                                        <x-status-badge :label="'Expires '.$userDetail->memberProfile->membership_expires_on->format('d M Y')" tone="warning" />
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($userDetail->hasRole(\App\Enums\RoleName::PlatformAdmin->value))
                            <div class="panel-card-muted admin-detail-span-full px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Platform Admin Scope</div>
                                <div class="mt-2 grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-950 dark:text-white">{{ $userDetail->notifications_count }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Notifications created</div>
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-slate-950 dark:text-white">{{ $userDetail->fcm_tokens_count }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Registered devices</div>
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-slate-950 dark:text-white">{{ $userDetail->recorded_payments_count }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Payments recorded</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-premium-card>

                @if ($userDetail->ownedGyms->isNotEmpty())
                    <x-table-wrapper class="overflow-hidden">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <h3 class="panel-section-title">Owned Gyms</h3>
                            <p class="panel-section-copy">Ownership footprint with current platform billing context.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="panel-table min-w-[980px]">
                                <thead>
                                    <tr>
                                        <th>Gym</th>
                                        <th>Location</th>
                                        <th>Platform Billing</th>
                                        <th>Status</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($userDetail->ownedGyms as $gym)
                                        <tr>
                                            <td>
                                                <div class="font-semibold text-slate-950 dark:text-white">{{ $gym->name }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $gym->slug }}</div>
                                            </td>
                                            <td>{{ $gym->city ?: 'N/A' }}</td>
                                            <td>
                                                @if ($gym->currentPlatformSubscription)
                                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $gym->currentPlatformSubscription->plan?->name ?? 'Custom billing' }}</div>
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">₹{{ number_format((float) $gym->currentPlatformSubscription->billing_amount, 0) }} • {{ $gym->currentPlatformSubscription->status }}</div>
                                                @else
                                                    <span class="text-sm text-slate-500 dark:text-slate-400">No platform billing</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="flex flex-wrap gap-2">
                                                    <x-status-badge :label="$gym->approval_status ?: $gym->status ?: 'pending'" />
                                                    <x-status-badge :label="$gym->is_active ? 'Active' : 'Inactive'" :tone="$gym->is_active ? 'success' : 'danger'" />
                                                </div>
                                            </td>
                                            <td>
                                                <div class="flex justify-end gap-2">
                                                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gyms.show', $gym) }}">Gym</x-action-button>
                                                    <x-action-button as="a" href="{{ route('web.admin.gym-platform-subscriptions.create', ['gym' => $gym->id]) }}">Billing</x-action-button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-table-wrapper>
                @endif

                @if ($userDetail->managedTrainerProfile)
                    <x-premium-card class="overflow-hidden">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <h3 class="panel-section-title">Trainer Operations</h3>
                            <p class="panel-section-copy">Coaching profile, specialization, and current member assignment load.</p>
                        </div>
                        <div class="admin-detail-grid p-5">
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Coaching Summary</div>
                                <div class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $userDetail->managedTrainerProfile->specialization ?: 'No specialization' }}</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $userDetail->managedTrainerProfile->experience_years ?: 0 }} years experience</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($userDetail->managedTrainerProfile->specializations ?? [] as $specialization)
                                        <x-status-badge :label="$specialization" tone="info" />
                                    @endforeach
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Languages & Certification</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse ($userDetail->managedTrainerProfile->languages ?? [] as $language)
                                        <x-status-badge :label="$language" tone="neutral" />
                                    @empty
                                        <x-status-badge label="No languages added" tone="neutral" />
                                    @endforelse
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse ($userDetail->managedTrainerProfile->certifications ?? [] as $certification)
                                        <x-status-badge :label="$certification" tone="verified" />
                                    @empty
                                        <x-status-badge label="No certifications added" tone="neutral" />
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted admin-detail-span-full px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Assigned Members</div>
                                    <x-status-badge :label="$userDetail->assigned_members_count.' assigned'" tone="warning" />
                                </div>
                                <div class="admin-detail-grid-compact mt-3">
                                    @forelse ($userDetail->managedTrainerProfile->assignedMembers as $assignedMember)
                                        <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                                            <div class="text-sm font-semibold text-slate-950 dark:text-white">{{ $assignedMember->user?->name ?? 'Member' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $assignedMember->gym?->name ?? 'No gym' }}{{ $assignedMember->branch?->name ? ' • '.$assignedMember->branch->name : '' }}</div>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <x-status-badge :label="$assignedMember->membership_status ?: 'inactive'" />
                                            </div>
                                        </div>
                                    @empty
                                        <div class="md:col-span-2">
                                            <x-empty-state title="No assigned members" message="This trainer does not have any linked members right now." />
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </x-premium-card>
                @endif

                @if ($userDetail->memberMemberships->isNotEmpty())
                    <x-table-wrapper class="overflow-hidden">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <h3 class="panel-section-title">Membership History</h3>
                            <p class="panel-section-copy">Membership status, plan mapping, and payment state for member accounts.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="panel-table min-w-[1100px]">
                                <thead>
                                    <tr>
                                        <th>Gym / Branch</th>
                                        <th>Plan</th>
                                        <th>Duration</th>
                                        <th>Commercials</th>
                                        <th>Approval</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($userDetail->memberMemberships as $membership)
                                        <tr>
                                            <td>
                                                <div class="font-semibold text-slate-950 dark:text-white">{{ $membership->gym?->name ?? 'No gym' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->branch?->name ?? 'No branch' }}</div>
                                            </td>
                                            <td>
                                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ $membership->membershipPlan?->name ?? 'No plan' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->membershipPlan?->cadence_label ?? 'Custom cadence' }}</div>
                                            </td>
                                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                                <div>Start {{ $membership->start_date?->format('d M Y') ?? 'N/A' }}</div>
                                                <div>End {{ $membership->expiry_date?->format('d M Y') ?? 'N/A' }}</div>
                                                @if ($membership->due_date)
                                                    <div>Due {{ $membership->due_date->format('d M Y') }}</div>
                                                @endif
                                            </td>
                                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                                <div>Payable ₹{{ number_format((float) $membership->final_payable_amount, 0) }}</div>
                                                <div>Paid ₹{{ number_format((float) $membership->amount_paid, 0) }}</div>
                                                <div>Due ₹{{ number_format((float) $membership->due_amount, 0) }}</div>
                                            </td>
                                            <td class="text-sm text-slate-600 dark:text-slate-300">
                                                <div>{{ $membership->approver?->name ?? 'Not approved' }}</div>
                                                @if ($membership->custom_fee_enabled)
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Custom fee enabled</div>
                                                @endif
                                                @if ($membership->custom_fee_reason)
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $membership->custom_fee_reason }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="flex flex-wrap gap-2">
                                                    <x-status-badge :label="$membership->status" />
                                                    <x-status-badge :label="$membership->payment_status" tone="verified" />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-table-wrapper>
                @endif

                @if ($userDetail->memberProfile)
                    <x-premium-card class="overflow-hidden">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <h3 class="panel-section-title">Member Profile Detail</h3>
                            <p class="panel-section-copy">All currently linked member profile, health, trainer, and emergency data.</p>
                        </div>
                        <div class="admin-detail-grid p-5">
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Fitness Profile</div>
                                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Goal:</span> {{ $userDetail->memberProfile->fitness_goal ?: 'N/A' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Experience:</span> {{ $userDetail->memberProfile->experience_level ?: 'N/A' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Gender:</span> {{ $userDetail->memberProfile->gender ?: 'N/A' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Height:</span> {{ $userDetail->memberProfile->height_cm ? $userDetail->memberProfile->height_cm.' cm' : 'N/A' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Weight:</span> {{ $userDetail->memberProfile->weight_kg ? $userDetail->memberProfile->weight_kg.' kg' : 'N/A' }}</div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @forelse ($userDetail->memberProfile->fitnessGoals as $fitnessGoal)
                                        <x-status-badge :label="$fitnessGoal->name" tone="info" />
                                    @empty
                                        <x-status-badge label="No linked goal tags" tone="neutral" />
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Trainer & Safety</div>
                                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Assigned trainer:</span> {{ $userDetail->memberProfile->assignedTrainer?->name ?? 'Not assigned' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Medical notes:</span> {{ $userDetail->memberProfile->medical_notes ?: 'None' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Injury notes:</span> {{ $userDetail->memberProfile->injury_notes ?: 'None' }}</div>
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Emergency Contact</div>
                                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Name:</span> {{ $userDetail->memberProfile->emergency_contact_name ?: 'N/A' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Phone:</span> {{ $userDetail->memberProfile->emergency_contact_phone ?: 'N/A' }}</div>
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Member State</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-status-badge :label="$userDetail->memberProfile->status ?: 'active'" />
                                    <x-status-badge :label="$userDetail->memberProfile->membership_status ?: 'inactive'" tone="verified" />
                                    <x-status-badge :label="$userDetail->memberProfile->is_active ? 'Profile active' : 'Profile inactive'" :tone="$userDetail->memberProfile->is_active ? 'success' : 'danger'" />
                                </div>
                                @if ($userDetail->memberProfile->membership_expires_on)
                                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">Profile expiry {{ $userDetail->memberProfile->membership_expires_on->format('d M Y') }}</div>
                                @endif
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Account Metadata</div>
                                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Email verified:</span> {{ $userDetail->email_verified_at?->format('d M Y h:i A') ?? 'Not verified' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Auth provider:</span> {{ $userDetail->auth_provider ?: 'email' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Google ID:</span> {{ $userDetail->google_id ?: 'N/A' }}</div>
                                    <div><span class="font-semibold text-slate-950 dark:text-white">Firebase UID:</span> {{ $userDetail->firebase_uid ?: 'N/A' }}</div>
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Onboarding State</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-status-badge :label="$userDetail->member_onboarding_completed ? 'Member onboarding done' : 'Member onboarding pending'" :tone="$userDetail->member_onboarding_completed ? 'success' : 'warning'" />
                                    <x-status-badge :label="'Step '.($userDetail->member_onboarding_step ?? 0)" tone="neutral" />
                                    <x-status-badge :label="$userDetail->trainer_onboarding_completed ? 'Trainer onboarding done' : 'Trainer onboarding pending'" :tone="$userDetail->trainer_onboarding_completed ? 'success' : 'neutral'" />
                                </div>
                                <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">Trainer onboarding step {{ $userDetail->trainer_onboarding_step ?? 0 }}</div>
                            </div>
                            <div class="panel-card-muted admin-detail-span-full px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Trainer Notes</div>
                                    <x-status-badge :label="$userDetail->memberProfile->trainerNotes->count().' notes'" tone="neutral" />
                                </div>
                                <div class="admin-detail-grid-compact mt-3">
                                    @forelse ($userDetail->memberProfile->trainerNotes->sortByDesc('follow_up_date')->take(6) as $note)
                                        <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="text-sm font-semibold text-slate-950 dark:text-white">{{ $note->trainer?->name ?? 'Trainer note' }}</div>
                                                <x-status-badge :label="$note->visibility ?: 'private'" tone="neutral" />
                                            </div>
                                            <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $note->note }}</div>
                                            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                                Follow up {{ $note->follow_up_date?->format('d M Y') ?? 'N/A' }} • {{ $note->completed_at ? 'Completed '.$note->completed_at->format('d M Y h:i A') : 'Open' }}
                                            </div>
                                        </div>
                                    @empty
                                        <div class="md:col-span-2 text-sm text-slate-500 dark:text-slate-400">No trainer notes linked to this member.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </x-premium-card>

                    <x-table-wrapper class="overflow-hidden">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <h3 class="panel-section-title">Payments</h3>
                            <p class="panel-section-copy">Payment ledger linked to this member across memberships.</p>
                        </div>
                        @if ($userDetail->memberProfile->payments->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="panel-table min-w-[1180px]">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Gym / Branch</th>
                                            <th>Membership</th>
                                            <th>Commercials</th>
                                            <th>Status</th>
                                            <th>Collection</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($userDetail->memberProfile->payments->sortByDesc('payment_date') as $payment)
                                            <tr>
                                                <td>{{ $payment->payment_date?->format('d M Y h:i A') ?? $payment->paid_at?->format('d M Y h:i A') ?? 'N/A' }}</td>
                                                <td>
                                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $payment->gym?->name ?? 'No gym' }}</div>
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $payment->branch?->name ?? 'No branch' }}</div>
                                                </td>
                                                <td>{{ $payment->membership?->membershipPlan?->name ?? 'No linked plan' }}</td>
                                                <td class="text-sm text-slate-600 dark:text-slate-300">
                                                    <div>₹{{ number_format((float) $payment->amount, 0) }}</div>
                                                    <div>{{ $payment->payment_mode ?: 'Unknown mode' }}</div>
                                                    @if ($payment->receipt_number)
                                                        <div>Receipt {{ $payment->receipt_number }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="flex flex-wrap gap-2">
                                                        <x-status-badge :label="$payment->status ?: 'recorded'" />
                                                        @if ($payment->payment_status)
                                                            <x-status-badge :label="$payment->payment_status" tone="verified" />
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-sm text-slate-600 dark:text-slate-300">
                                                    <div>{{ $payment->receiver?->name ?? $payment->collector?->name ?? 'N/A' }}</div>
                                                    @if ($payment->external_reference)
                                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ref {{ $payment->external_reference }}</div>
                                                    @endif
                                                    @if ($payment->receipt?->id || $payment->receipt_number)
                                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Receipt {{ $payment->receipt_number ?? '#'.$payment->receipt->id }}</div>
                                                    @endif
                                                    @if ($payment->notes)
                                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $payment->notes }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="px-5 py-6">
                                <x-empty-state title="No payments recorded" message="This member does not have any linked payments yet." />
                            </div>
                        @endif
                    </x-table-wrapper>

                    <x-table-wrapper class="overflow-hidden">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <h3 class="panel-section-title">Attendance</h3>
                            <p class="panel-section-copy">Check-in history, methods, and operators.</p>
                        </div>
                        @if ($userDetail->memberProfile->attendanceLogs->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="panel-table min-w-[1080px]">
                                    <thead>
                                        <tr>
                                            <th>Checked In</th>
                                            <th>Gym / Branch</th>
                                            <th>Method</th>
                                            <th>Recorded By</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($userDetail->memberProfile->attendanceLogs->sortByDesc('checked_in_at') as $attendance)
                                            <tr>
                                                <td>{{ $attendance->checked_in_at?->format('d M Y h:i A') ?? 'N/A' }}</td>
                                                <td>
                                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $attendance->gym?->name ?? 'No gym' }}</div>
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $attendance->branch?->name ?? 'No branch' }}</div>
                                                </td>
                                                <td>{{ str($attendance->check_in_method ?: 'manual')->replace('_', ' ')->title() }}</td>
                                                <td>{{ $attendance->checkedInByUser?->name ?? 'N/A' }}</td>
                                                <td class="text-sm text-slate-600 dark:text-slate-300">
                                                    <div>{{ $attendance->notes ?: 'No notes' }}</div>
                                                    @if ($attendance->source_device)
                                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Device {{ $attendance->source_device }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="px-5 py-6">
                                <x-empty-state title="No attendance records" message="Attendance history will appear once this member checks in." />
                            </div>
                        @endif
                    </x-table-wrapper>

                    <x-premium-card class="overflow-hidden">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <h3 class="panel-section-title">Progress and Measurements</h3>
                            <p class="panel-section-copy">Weight logs, body measurements, progress photos, steps, and personal records.</p>
                        </div>
                        <div class="admin-detail-grid p-5">
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Weight Logs</div>
                                    <x-status-badge :label="$userDetail->memberProfile->weightLogs->count().' entries'" tone="neutral" />
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($userDetail->memberProfile->weightLogs->sortByDesc('log_date')->take(5) as $log)
                                        <div class="text-sm text-slate-600 dark:text-slate-300">{{ $log->log_date?->format('d M Y') ?? 'N/A' }} • {{ $log->weight_kg }} kg • {{ $log->logger?->name ?? 'Unknown logger' }}</div>
                                    @empty
                                        <div class="text-sm text-slate-500 dark:text-slate-400">No weight logs.</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Body Measurements</div>
                                    <x-status-badge :label="$userDetail->memberProfile->bodyMeasurements->count().' entries'" tone="neutral" />
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($userDetail->memberProfile->bodyMeasurements->sortByDesc('measured_on')->take(5) as $measurement)
                                        <div class="text-sm text-slate-600 dark:text-slate-300">{{ $measurement->measured_on?->format('d M Y') ?? 'N/A' }} • Chest {{ $measurement->chest_cm ?: '—' }} • Waist {{ $measurement->waist_cm ?: '—' }} • Fat {{ $measurement->body_fat_percentage ?: '—' }}%</div>
                                    @empty
                                        <div class="text-sm text-slate-500 dark:text-slate-400">No measurements.</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Progress Photos</div>
                                    <x-status-badge :label="$userDetail->memberProfile->progressPhotos->count().' photos'" tone="neutral" />
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($userDetail->memberProfile->progressPhotos->sortByDesc('captured_on')->take(5) as $photo)
                                        <div class="text-sm text-slate-600 dark:text-slate-300">{{ $photo->captured_on?->format('d M Y') ?? 'N/A' }} • {{ $photo->photo_type ?: 'photo' }} • {{ $photo->uploader?->name ?? 'Unknown uploader' }}</div>
                                        @if ($photo->album_key || $photo->photo_url)
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $photo->album_key ?: $photo->photo_url }}</div>
                                        @endif
                                    @empty
                                        <div class="text-sm text-slate-500 dark:text-slate-400">No progress photos.</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Daily Steps</div>
                                    <x-status-badge :label="$userDetail->daily_steps_count.' entries'" tone="neutral" />
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($userDetail->dailySteps->sortByDesc('step_date')->take(5) as $step)
                                        <div class="text-sm text-slate-600 dark:text-slate-300">{{ $step->step_date?->format('d M Y') ?? 'N/A' }} • {{ number_format((int) $step->steps) }} steps • Goal {{ number_format((int) ($step->goal_steps ?? 0)) }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Calories {{ number_format((int) ($step->calories_estimated ?? 0)) }} • Distance {{ number_format((int) ($step->distance_meters ?? 0)) }} m • {{ $step->source ?: 'manual' }}</div>
                                    @empty
                                        <div class="text-sm text-slate-500 dark:text-slate-400">No step history.</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted admin-detail-span-full px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Personal Records</div>
                                    <x-status-badge :label="$userDetail->memberProfile->personalRecords->count().' records'" tone="neutral" />
                                </div>
                                <div class="admin-detail-grid-compact mt-3">
                                    @forelse ($userDetail->memberProfile->personalRecords->sortByDesc('achieved_at')->take(6) as $record)
                                        <div class="rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/80">
                                            <div class="text-sm font-semibold text-slate-950 dark:text-white">{{ $record->exercise?->name ?? 'Exercise' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $record->achieved_at?->format('d M Y h:i A') ?? 'N/A' }}</div>
                                            <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">Weight {{ $record->best_weight ?: '—' }} • Reps {{ $record->best_reps ?: '—' }} • Volume {{ $record->best_volume ?: '—' }}</div>
                                            @if ($record->workoutSession)
                                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Session {{ $record->workoutSession->session_date?->format('d M Y') ?? 'N/A' }} • {{ $record->workoutSession->status }}</div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="md:col-span-2 text-sm text-slate-500 dark:text-slate-400">No personal records.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </x-premium-card>

                    <x-premium-card class="overflow-hidden">
                        <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                            <h3 class="panel-section-title">Reminders, Trials, and Workouts</h3>
                            <p class="panel-section-copy">Engagement and activity data linked to the member account.</p>
                        </div>
                        <div class="admin-detail-grid p-5">
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Scheduled Reminders</div>
                                    <x-status-badge :label="$userDetail->scheduledReminders->count().' reminders'" tone="neutral" />
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($userDetail->scheduledReminders->sortByDesc('scheduled_for')->take(5) as $reminder)
                                        <div class="text-sm text-slate-600 dark:text-slate-300">{{ $reminder->scheduled_for?->format('d M Y h:i A') ?? 'N/A' }} • {{ $reminder->title }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $reminder->type ?: 'reminder' }} • {{ $reminder->status ?: 'scheduled' }}{{ $reminder->membership?->membershipPlan?->name ? ' • '.$reminder->membership->membershipPlan->name : '' }}</div>
                                    @empty
                                        <div class="text-sm text-slate-500 dark:text-slate-400">No reminders.</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Trial Requests</div>
                                    <x-status-badge :label="$userDetail->trial_requests_count.' requests'" tone="neutral" />
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($userDetail->trialRequests->sortByDesc('preferred_date')->take(5) as $trial)
                                        <div class="text-sm text-slate-600 dark:text-slate-300">{{ $trial->gym?->name ?? 'No gym' }} • {{ $trial->preferred_date?->format('d M Y') ?? 'N/A' }} • {{ $trial->status }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $trial->assignedTrainer?->name ?? 'No trainer assigned' }}{{ $trial->preferred_time ? ' • '.$trial->preferred_time : '' }}</div>
                                    @empty
                                        <div class="text-sm text-slate-500 dark:text-slate-400">No trial requests.</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Workout Plans</div>
                                    <x-status-badge :label="$userDetail->workout_plans_as_member_count.' plans'" tone="neutral" />
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($userDetail->workoutPlansAsMember->sortByDesc('assigned_at')->take(5) as $plan)
                                        <div class="text-sm text-slate-600 dark:text-slate-300">{{ $plan->name }} • {{ $plan->status }} • {{ $plan->trainer?->name ?? 'No trainer' }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $plan->goal ?: 'No goal' }} • {{ $plan->difficulty ?: 'No difficulty' }} • {{ $plan->duration_weeks ?: '—' }} weeks • {{ $plan->creator?->name ?? 'System' }}</div>
                                    @empty
                                        <div class="text-sm text-slate-500 dark:text-slate-400">No workout plans.</div>
                                    @endforelse
                                </div>
                            </div>
                            <div class="panel-card-muted px-4 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Workout Sessions</div>
                                    <x-status-badge :label="$userDetail->workout_sessions_as_member_count.' sessions'" tone="neutral" />
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($userDetail->workoutSessionsAsMember->sortByDesc('session_date')->take(5) as $session)
                                        <div class="text-sm text-slate-600 dark:text-slate-300">{{ $session->session_date?->format('d M Y') ?? 'N/A' }} • {{ $session->status }} • {{ $session->trainer?->name ?? 'No trainer' }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $session->plan?->name ?? 'No plan' }} • Started by {{ $session->starter?->name ?? 'N/A' }} • Volume {{ $session->total_volume ?: '—' }}</div>
                                    @empty
                                        <div class="text-sm text-slate-500 dark:text-slate-400">No workout sessions.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </x-premium-card>
                @endif

            </div>

            <div class="space-y-6">
                <x-premium-card class="p-5">
                    <h3 class="panel-section-title">Account Status</h3>
                    <p class="panel-section-copy">Activate or deactivate this account safely from platform admin.</p>
                    <div class="mt-4">
                        @if ($userDetail->is_active)
                            @if (auth()->id() === $userDetail->id)
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                                    You cannot deactivate your own platform admin account from this panel.
                                </div>
                            @else
                                <form method="POST" action="{{ route('web.admin.users.deactivate', $userDetail) }}" onsubmit="return confirm('Deactivate this user?');">
                                    @csrf
                                    <x-action-button type="submit" variant="danger" class="w-full">Deactivate User</x-action-button>
                                </form>
                            @endif
                        @else
                            <form method="POST" action="{{ route('web.admin.users.activate', $userDetail) }}">
                                @csrf
                                <x-action-button type="submit" class="w-full">Activate User</x-action-button>
                            </form>
                        @endif
                    </div>
                </x-premium-card>

                @if ($userDetail->staffAssignments->isNotEmpty())
                    <x-premium-card class="p-5">
                        <h3 class="panel-section-title">Staff Assignments</h3>
                        <div class="mt-4 space-y-3">
                            @foreach ($userDetail->staffAssignments as $assignment)
                                <div class="panel-card-muted px-4 py-4">
                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $assignment->gym?->name ?? 'Gym' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $assignment->branch?->name ?? 'No branch' }}</div>
                                    <div class="mt-2">
                                        <x-status-badge :label="str($assignment->role_name ?: 'staff')->replace('_', ' ')->title()" tone="neutral" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-premium-card>
                @endif
            </div>
        </div>

        @include('web.admin.partials.activity-log-section', [
            'title' => 'Activity Intelligence',
            'description' => 'Recent trust signals for this account, with a dedicated history page for the full audit trail.',
            'activityStats' => $activityStats,
            'activityTimeline' => $activityTimeline,
            'activityRows' => $activityRows,
            'activityLatestLabel' => $activityLatestLabel,
            'historyUrl' => route('web.admin.users.activity', $userDetail),
            'emptyTitle' => 'No user activity yet',
            'emptyMessage' => 'User-level platform audit activity will appear here as actions are recorded.',
        ])
    </div>
@endsection
