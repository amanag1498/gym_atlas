@php
    $member = $member ?? null;
    $memberProfile = $memberProfile ?? null;
@endphp

<div class="space-y-5">
    @if (! $member)
        <div>
            <label for="existing_user_id" class="panel-label">Select Existing User</label>
            <select id="existing_user_id" name="existing_user_id" class="panel-select">
                <option value="">Create a new member user</option>
                @foreach ($existingUsers as $existingUser)
                    @php($existingProfile = $existingUser->memberProfile)
                    <option
                        value="{{ $existingUser->id }}"
                        data-name="{{ $existingUser->name }}"
                        data-email="{{ $existingUser->email }}"
                        @if ($hasPhoneColumn) data-phone="{{ $existingUser->phone }}" @endif
                        data-avatar="{{ $existingUser->avatar }}"
                        data-fitness-goal="{{ $existingProfile?->fitness_goal }}"
                        data-experience-level="{{ $existingProfile?->experience_level }}"
                        data-height-cm="{{ $existingProfile?->height_cm }}"
                        data-weight-kg="{{ $existingProfile?->weight_kg }}"
                        data-gender="{{ $existingProfile?->gender }}"
                        data-medical-notes="{{ $existingProfile?->medical_notes }}"
                        data-injury-notes="{{ $existingProfile?->injury_notes }}"
                        data-emergency-contact-name="{{ $existingProfile?->emergency_contact_name }}"
                        data-emergency-contact-phone="{{ $existingProfile?->emergency_contact_phone }}"
                        data-biometric-identifier="{{ $existingProfile?->biometric_identifier }}"
                        data-biometric-enabled="{{ $existingProfile?->biometric_enabled ? '1' : '0' }}"
                        @selected(old('existing_user_id') == $existingUser->id)
                    >
                        {{ $existingUser->name }} • {{ $existingUser->email }}
                    </option>
                @endforeach
            </select>
            <div id="existing_user_hint" class="mt-3 hidden rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                Existing user selected. This will send a pending gym invitation; the user must accept before becoming a member of this gym.
            </div>
        </div>
    @endif

    <div class="grid gap-5 md:grid-cols-2">
        <div data-existing-account-field>
            <x-form-input name="name" label="Full Name" :value="$member?->name" required />
        </div>
        <div data-existing-account-field>
            <x-form-input name="email" label="Email" type="email" :value="$member?->email" required />
        </div>
        @if ($hasPhoneColumn)
            <div data-existing-account-field>
                <x-form-input name="phone" label="Phone" :value="$member?->phone" />
            </div>
        @endif
        <div data-existing-account-field>
            <x-form-input name="avatar" label="Photo URL" :value="$member?->avatar" placeholder="https://..." />
        </div>
        <div>
            <label for="branch_id" class="panel-label">Branch</label>
            <select id="branch_id" name="branch_id" class="panel-select">
                <option value="">Select branch</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((int) old('branch_id', $memberProfile?->branch_id) === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="assigned_trainer_user_id" class="panel-label">Assigned Trainer</label>
            <select id="assigned_trainer_user_id" name="assigned_trainer_user_id" class="panel-select">
                <option value="">No trainer assigned</option>
                @foreach ($trainers as $trainer)
                    <option
                        value="{{ $trainer->id }}"
                        data-branch-id="{{ $trainer->managedTrainerProfile?->branch_id }}"
                        @selected((int) old('assigned_trainer_user_id', $memberProfile?->assigned_trainer_user_id) === $trainer->id)
                    >
                        {{ $trainer->name }}
                        @if ($trainer->managedTrainerProfile?->branch?->name)
                            • {{ $trainer->managedTrainerProfile->branch->name }}
                        @endif
                    </option>
                @endforeach
            </select>
            <p id="trainer_branch_hint" class="mt-2 hidden text-xs text-amber-700">Trainer selection was cleared because that trainer is not assigned to the selected branch.</p>
        </div>
        <div data-existing-profile-field>
            <x-form-input name="fitness_goal" label="Fitness Goal" :value="$memberProfile?->fitness_goal" />
        </div>
        <div data-existing-profile-field>
            <x-form-input name="experience_level" label="Experience Level" :value="$memberProfile?->experience_level" />
        </div>
        <div data-existing-profile-field>
            <x-form-input name="height_cm" label="Height (cm)" :value="$memberProfile?->height_cm" />
        </div>
        <div data-existing-profile-field>
            <x-form-input name="weight_kg" label="Weight (kg)" :value="$memberProfile?->weight_kg" />
        </div>
        <div>
            <label for="status" class="panel-label">Status</label>
            <select id="status" name="status" class="panel-select">
                <option value="active" @selected(old('status', $memberProfile?->membership_status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $memberProfile?->membership_status) === 'inactive')>Inactive</option>
                <option value="expired" @selected(old('status', $memberProfile?->membership_status) === 'expired')>Expired</option>
            </select>
        </div>
    </div>

    @if (! $member)
        <div class="overflow-hidden rounded-[1.35rem] border border-sky-100 bg-sky-50/60 dark:border-sky-500/20 dark:bg-sky-500/10">
            <div class="border-b border-sky-100 bg-white/70 px-5 py-4 dark:border-sky-500/20 dark:bg-slate-950/60">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300">Membership Setup</p>
                <h4 class="mt-1 text-lg font-semibold tracking-tight text-slate-950 dark:text-white">Attach a plan during intake</h4>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Optional. For existing app users, this plan is applied only after they accept the gym invitation.</p>
            </div>

            <div class="grid gap-5 px-5 py-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="membership_plan_id" class="panel-label">Membership Plan</label>
                    <select id="membership_plan_id" name="membership_plan_id" class="panel-select">
                        <option value="">Assign later</option>
                        @foreach (($plans ?? collect()) as $plan)
                            <option
                                value="{{ $plan->id }}"
                                data-branch-id="{{ $plan->branch_id }}"
                                data-duration-days="{{ (int) $plan->duration_days }}"
                                data-plan-price="{{ number_format((float) $plan->plan_price, 2, '.', '') }}"
                                data-joining-fee="{{ number_format((float) $plan->joining_fee, 2, '.', '') }}"
                                @selected((int) old('membership_plan_id') === $plan->id)
                            >
                                {{ $plan->name }} • {{ $plan->duration_label }} • {{ $plan->price_label }}
                                @if ($plan->branch?->name)
                                    • {{ $plan->branch->name }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">A branch is required when assigning a membership plan.</p>
                </div>

                <div>
                    <label for="start_date" class="panel-label">Start Date</label>
                    <input id="start_date" type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" class="panel-input">
                </div>
                <div>
                    <label for="due_date" class="panel-label">Due Date</label>
                    <input id="due_date" type="date" name="due_date" value="{{ old('due_date') }}" class="panel-input">
                </div>
                <div>
                    <label for="amount_paid" class="panel-label">Initial Payment</label>
                    <input id="amount_paid" name="amount_paid" value="{{ old('amount_paid', 0) }}" class="panel-input">
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">For existing users, collect payment after they accept the invitation.</p>
                </div>
                <div>
                    <label for="expiry_date" class="panel-label">Custom Expiry Date</label>
                    <input id="expiry_date" type="date" name="expiry_date" value="{{ old('expiry_date') }}" class="panel-input">
                </div>

                <div class="md:col-span-2 rounded-2xl border border-white/80 bg-white/70 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/60">
                    <input type="hidden" name="custom_fee_enabled" value="0">
                    <label class="flex items-center gap-3 text-sm font-medium text-slate-800 dark:text-slate-100">
                        <input type="checkbox" name="custom_fee_enabled" value="1" @checked(old('custom_fee_enabled'))>
                        Enable member-specific custom fee
                    </label>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Use this for discounted joining, custom plan price, PT fee, or partial month billing. The original plan price is not changed.</p>
                </div>

                <div>
                    <label for="custom_fee_amount" class="panel-label">Custom Plan Fee</label>
                    <input id="custom_fee_amount" name="custom_fee_amount" value="{{ old('custom_fee_amount') }}" class="panel-input">
                </div>
                <div>
                    <label for="custom_joining_fee" class="panel-label">Custom Joining Fee</label>
                    <input id="custom_joining_fee" name="custom_joining_fee" value="{{ old('custom_joining_fee') }}" class="panel-input">
                </div>
                <div>
                    <label for="discount_type" class="panel-label">Discount Type</label>
                    <select id="discount_type" name="discount_type" class="panel-select">
                        <option value="none" @selected(old('discount_type', 'none') === 'none')>None</option>
                        <option value="fixed" @selected(old('discount_type') === 'fixed')>Fixed</option>
                        <option value="percentage" @selected(old('discount_type') === 'percentage')>Percentage</option>
                    </select>
                </div>
                <div>
                    <label for="discount_amount" class="panel-label">Discount Amount</label>
                    <input id="discount_amount" name="discount_amount" value="{{ old('discount_amount') }}" class="panel-input">
                </div>
                <div>
                    <label for="partial_month_fee" class="panel-label">Partial Month Fee</label>
                    <input id="partial_month_fee" name="partial_month_fee" value="{{ old('partial_month_fee') }}" class="panel-input">
                </div>
                <div>
                    <label for="pt_custom_fee" class="panel-label">PT Custom Fee</label>
                    <input id="pt_custom_fee" name="pt_custom_fee" value="{{ old('pt_custom_fee') }}" class="panel-input">
                </div>
                <div class="md:col-span-2">
                    <label for="custom_fee_reason" class="panel-label">Custom Fee Reason</label>
                    <textarea id="custom_fee_reason" name="custom_fee_reason" class="panel-textarea">{{ old('custom_fee_reason') }}</textarea>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-5 md:grid-cols-2">
        <div data-existing-profile-field>
            <label for="medical_notes" class="panel-label">Medical Notes</label>
            <textarea id="medical_notes" name="medical_notes" class="panel-textarea">{{ old('medical_notes', $memberProfile?->medical_notes) }}</textarea>
        </div>
        <div data-existing-profile-field>
            <label for="injury_notes" class="panel-label">Injury Notes</label>
            <textarea id="injury_notes" name="injury_notes" class="panel-textarea">{{ old('injury_notes', $memberProfile?->injury_notes) }}</textarea>
        </div>
        <div data-existing-profile-field>
            <x-form-input name="emergency_contact_name" label="Emergency Contact Name" :value="$memberProfile?->emergency_contact_name" />
        </div>
        <div data-existing-profile-field>
            <x-form-input name="emergency_contact_phone" label="Emergency Contact Phone" :value="$memberProfile?->emergency_contact_phone" />
        </div>
        <div data-existing-profile-field>
            <x-form-input name="biometric_identifier" label="Biometric Identifier" :value="$memberProfile?->biometric_identifier" placeholder="Scanner member code / biometric template id" />
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">This identifier is matched by the biometric attendance desk during scan.</p>
        </div>
        <div data-existing-profile-field class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/70">
            <input type="hidden" name="biometric_enabled" value="0">
            <label class="flex items-center gap-3 text-sm font-medium text-slate-800 dark:text-slate-100">
                <input type="checkbox" name="biometric_enabled" value="1" @checked(old('biometric_enabled', $memberProfile?->biometric_enabled))>
                Enable biometric attendance
            </label>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Attendance scan access is granted only when biometric is enabled and the identifier is set.</p>
        </div>
    </div>
</div>

@if (! $member)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const select = document.getElementById('existing_user_id');
                if (!select) return;

                const hint = document.getElementById('existing_user_hint');
                const accountFields = [...document.querySelectorAll('[data-existing-account-field]')];
                const profileFields = [...document.querySelectorAll('[data-existing-profile-field]')];
                const fieldMap = {
                    name: 'name',
                    email: 'email',
                    phone: 'phone',
                    avatar: 'avatar',
                    fitness_goal: 'fitnessGoal',
                    experience_level: 'experienceLevel',
                    height_cm: 'heightCm',
                    weight_kg: 'weightKg',
                    gender: 'gender',
                    medical_notes: 'medicalNotes',
                    injury_notes: 'injuryNotes',
                    emergency_contact_name: 'emergencyContactName',
                    emergency_contact_phone: 'emergencyContactPhone',
                    biometric_identifier: 'biometricIdentifier',
                };

                const setSectionState = (fields, hidden, disabled = false) => {
                    fields.forEach((field) => {
                        field.classList.toggle('hidden', hidden);
                        field.querySelectorAll('input, textarea, select').forEach((input) => {
                            input.disabled = disabled;
                        });
                    });
                };

                const fillFromSelectedUser = () => {
                    const selected = select.selectedOptions[0];
                    const hasExistingUser = Boolean(select.value);

                    hint?.classList.toggle('hidden', !hasExistingUser);
                    setSectionState(accountFields, hasExistingUser, hasExistingUser);
                    setSectionState(profileFields, hasExistingUser, false);

                    if (!selected || !hasExistingUser) return;

                    Object.entries(fieldMap).forEach(([fieldName, datasetKey]) => {
                        const input = document.querySelector(`[name="${fieldName}"]`);
                        if (input && selected.dataset[datasetKey] !== undefined) {
                            input.value = selected.dataset[datasetKey] ?? '';
                        }
                    });

                    const biometricEnabled = document.querySelector('input[name="biometric_enabled"][value="1"]');
                    if (biometricEnabled) {
                        biometricEnabled.checked = selected.dataset.biometricEnabled === '1';
                    }
                };

                select.addEventListener('change', fillFromSelectedUser);
                fillFromSelectedUser();
            });
        </script>
    @endpush
@endif

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const branchSelect = document.getElementById('branch_id');
            const trainerSelect = document.getElementById('assigned_trainer_user_id');
            const hint = document.getElementById('trainer_branch_hint');
            const planSelect = document.getElementById('membership_plan_id');
            const startDateInput = document.getElementById('start_date');
            const dueDateInput = document.getElementById('due_date');
            const expiryDateInput = document.getElementById('expiry_date');
            const amountPaidInput = document.getElementById('amount_paid');
            const customFeeEnabledInput = document.querySelector('input[name="custom_fee_enabled"][value="1"]');
            const customFeeInput = document.getElementById('custom_fee_amount');
            const customJoiningFeeInput = document.getElementById('custom_joining_fee');
            const discountTypeInput = document.getElementById('discount_type');
            const discountAmountInput = document.getElementById('discount_amount');
            const partialMonthFeeInput = document.getElementById('partial_month_fee');
            const ptCustomFeeInput = document.getElementById('pt_custom_fee');

            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = `${date.getMonth() + 1}`.padStart(2, '0');
                const day = `${date.getDate()}`.padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const addDays = (dateValue, days) => {
                const base = dateValue ? new Date(`${dateValue}T00:00:00`) : new Date();
                if (Number.isNaN(base.getTime())) {
                    return '';
                }
                base.setDate(base.getDate() + Math.max(0, days));
                return formatDate(base);
            };

            const filterTrainersForBranch = () => {
                if (!branchSelect || !trainerSelect) return;

                const branchId = branchSelect.value;
                let selectedStillVisible = true;

                [...trainerSelect.options].forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    const optionBranchId = option.dataset.branchId || '';
                    const visible = !branchId || optionBranchId === branchId;
                    option.hidden = !visible;

                    if (option.selected && !visible) {
                        selectedStillVisible = false;
                    }
                });

                if (!selectedStillVisible) {
                    trainerSelect.value = '';
                    hint?.classList.remove('hidden');
                } else {
                    hint?.classList.add('hidden');
                }
            };

            const applyPlanDefaults = (force = false) => {
                if (!planSelect) return;

                const selected = planSelect.selectedOptions[0];
                if (!selected || !selected.value) return;

                const branchId = selected.dataset.branchId || '';
                const durationDays = parseInt(selected.dataset.durationDays || '0', 10);
                const planPrice = parseFloat(selected.dataset.planPrice || '0');
                const joiningFee = parseFloat(selected.dataset.joiningFee || '0');
                const startDate = startDateInput?.value || formatDate(new Date());
                const expiryDate = durationDays > 0 ? addDays(startDate, durationDays) : '';
                const totalPayable = Math.max(0, planPrice + joiningFee);

                if (branchId && branchSelect && (!branchSelect.value || force)) {
                    branchSelect.value = branchId;
                    branchSelect.dispatchEvent(new Event('change'));
                }

                if (startDateInput && (!startDateInput.value || force)) {
                    startDateInput.value = startDate;
                }
                if (expiryDateInput && (!expiryDateInput.value || force)) {
                    expiryDateInput.value = expiryDate;
                }
                if (dueDateInput && (!dueDateInput.value || force)) {
                    dueDateInput.value = expiryDate;
                }
                if (amountPaidInput && (!amountPaidInput.value || amountPaidInput.value === '0' || force)) {
                    amountPaidInput.value = totalPayable.toFixed(2);
                }
                if (customFeeEnabledInput?.checked) {
                    if (customFeeInput && (!customFeeInput.value || force)) {
                        customFeeInput.value = planPrice.toFixed(2);
                    }
                    if (customJoiningFeeInput && (!customJoiningFeeInput.value || force)) {
                        customJoiningFeeInput.value = joiningFee.toFixed(2);
                    }
                    if (discountTypeInput && (!discountTypeInput.value || force)) {
                        discountTypeInput.value = 'none';
                    }
                    if (discountAmountInput && (!discountAmountInput.value || force)) {
                        discountAmountInput.value = '0.00';
                    }
                    if (partialMonthFeeInput && (!partialMonthFeeInput.value || force)) {
                        partialMonthFeeInput.value = '0.00';
                    }
                    if (ptCustomFeeInput && (!ptCustomFeeInput.value || force)) {
                        ptCustomFeeInput.value = '0.00';
                    }
                }
            };

            branchSelect?.addEventListener('change', filterTrainersForBranch);
            planSelect?.addEventListener('change', () => applyPlanDefaults(true));
            customFeeEnabledInput?.addEventListener('change', () => {
                if (customFeeEnabledInput.checked) {
                    applyPlanDefaults(false);
                }
            });
            startDateInput?.addEventListener('change', () => {
                if (!planSelect?.value) return;
                const selected = planSelect.selectedOptions[0];
                const durationDays = parseInt(selected?.dataset.durationDays || '0', 10);
                if (expiryDateInput && durationDays > 0) {
                    expiryDateInput.value = addDays(startDateInput.value, durationDays);
                }
                if (dueDateInput && durationDays > 0) {
                    dueDateInput.value = addDays(startDateInput.value, durationDays);
                }
            });

            filterTrainersForBranch();
            applyPlanDefaults(false);
        });
    </script>
@endpush
