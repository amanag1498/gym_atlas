@php
    $trainer = $trainer ?? null;
    $trainerProfile = $trainerProfile ?? null;
@endphp

<div class="space-y-5">
    @if (! $trainer)
        <div>
            <label for="existing_user_id" class="panel-label">Select Existing User</label>
            <select id="existing_user_id" name="existing_user_id" class="panel-select">
                <option value="">Create a new trainer user</option>
                @foreach ($existingUsers as $existingUser)
                    @php($existingMemberProfile = $existingUser->memberProfiles->first())
                    <option
                        value="{{ $existingUser->id }}"
                        data-name="{{ $existingUser->name }}"
                        data-email="{{ $existingUser->email }}"
                        @if ($hasPhoneColumn) data-phone="{{ $existingUser->phone }}" @endif
                        data-avatar="{{ $existingUser->avatar }}"
                        @selected(old('existing_user_id') == $existingUser->id)
                    >
                        {{ $existingUser->name }} • {{ $existingUser->email }}
                        @if ($existingMemberProfile)
                            • Existing member
                        @elseif ($existingUser->hasRole(\App\Enums\RoleName::Trainer->value))
                            • Trainer user
                        @endif
                    </option>
                @endforeach
            </select>
            <div id="existing_user_hint" class="mt-3 hidden rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">
                Existing user selected. Account details are reused; only gym, branch, and coaching profile details are updated here.
            </div>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[1.04fr_0.96fr]">
        <div class="space-y-5">
            <div class="grid gap-5 md:grid-cols-2">
                <div data-existing-account-field>
                    <x-form-input name="name" label="Trainer Name" :value="$trainer?->name" required />
                </div>
                <div data-existing-account-field>
                    <x-form-input name="email" label="Email" :value="$trainer?->email" required />
                </div>
                @if ($hasPhoneColumn)
                    <div data-existing-account-field>
                        <x-form-input name="phone" label="Phone" :value="$trainer?->phone" />
                    </div>
                @endif
                <div data-existing-account-field>
                    <x-form-input name="profile_photo_url" label="Profile Photo" :value="$trainerProfile?->profile_photo_url" placeholder="https://..." />
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="branch_id" class="panel-label">Assigned Branch</label>
                    <select id="branch_id" name="branch_id" class="panel-select">
                        <option value="">Select branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) old('branch_id', $trainerProfile?->branch_id) === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="panel-label">Status</label>
                    <select id="status" name="status" class="panel-select">
                        <option value="active" @selected(old('status', $trainerProfile?->status ?? 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $trainerProfile?->status) === 'inactive')>Inactive</option>
                    </select>
                </div>
                <x-form-input name="specialization" label="Primary Specialization" :value="old('specialization', $trainerProfile?->specialization ?? (($trainerProfile?->specializations ?? [])[0] ?? null))" placeholder="Strength, Fat loss, Mobility..." />
                <x-form-input name="experience_years" label="Experience Years" type="number" min="0" :value="$trainerProfile?->experience_years" />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="specializations_text" class="panel-label">Specializations</label>
                    <textarea id="specializations_text" name="specializations_text" class="panel-textarea" placeholder="One per line">{{ old('specializations_text', implode(PHP_EOL, $trainerProfile?->specializations ?? [])) }}</textarea>
                </div>
                <div>
                    <label for="certifications_text" class="panel-label">Certifications</label>
                    <textarea id="certifications_text" name="certifications_text" class="panel-textarea" placeholder="One per line">{{ old('certifications_text', implode(PHP_EOL, $trainerProfile?->certifications ?? [])) }}</textarea>
                </div>
                <div>
                    <label for="languages_text" class="panel-label">Languages</label>
                    <textarea id="languages_text" name="languages_text" class="panel-textarea" placeholder="One per line">{{ old('languages_text', implode(PHP_EOL, $trainerProfile?->languages ?? [])) }}</textarea>
                </div>
                <div>
                    <label for="availability_notes" class="panel-label">Availability Notes</label>
                    <textarea id="availability_notes" name="availability_notes" class="panel-textarea" placeholder="Morning batches, evening slots, weekly off, etc.">{{ old('availability_notes', $trainerProfile?->availability_notes) }}</textarea>
                </div>
            </div>

            <div>
                <label for="bio" class="panel-label">Bio</label>
                <textarea id="bio" name="bio" class="panel-textarea" placeholder="Trainer background, coaching style, and focus areas.">{{ old('bio', $trainerProfile?->bio) }}</textarea>
            </div>
        </div>

        <div class="space-y-4">
            <div class="panel-card-muted px-4 py-4">
                <label for="verification_status" class="panel-label">Verification Status</label>
                <input id="verification_status" name="verification_status" type="text" value="{{ old('verification_status', $trainerProfile?->verification_status) }}" class="panel-input mt-2" placeholder="Verified, pending documents, in review..." />
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">This is an internal admin signal for trainer credential review.</p>
            </div>

            <div class="panel-card-muted px-4 py-4">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Profile Notes</p>
                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <p>Use one primary specialization for scanning speed.</p>
                    <p>Keep certifications and languages structured so member assignment stays reliable.</p>
                    <p>Availability notes should reflect actual slots, off-days, and special restrictions.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@if (! $trainer)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const select = document.getElementById('existing_user_id');
                if (!select) return;

                const hint = document.getElementById('existing_user_hint');
                const accountFields = [...document.querySelectorAll('[data-existing-account-field]')];
                const fieldMap = {
                    name: 'name',
                    email: 'email',
                    phone: 'phone',
                    profile_photo_url: 'avatar',
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

                    if (!selected || !hasExistingUser) return;

                    Object.entries(fieldMap).forEach(([fieldName, datasetKey]) => {
                        const input = document.querySelector(`[name="${fieldName}"]`);
                        if (input && selected.dataset[datasetKey] !== undefined) {
                            input.value = selected.dataset[datasetKey] ?? '';
                        }
                    });
                };

                select.addEventListener('change', fillFromSelectedUser);
                fillFromSelectedUser();
            });
        </script>
    @endpush
@endif
