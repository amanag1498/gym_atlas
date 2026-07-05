@php
    $staff = $staff ?? null;
    $currentPermissions = $currentPermissions ?? [];
    $defaultCustomPermissions = $defaultCustomPermissions ?? [];
    $selectedPermissions = old('custom_permissions', $staff ? $currentPermissions : $defaultCustomPermissions);
    $selectedRole = old('role', $staff?->roles->pluck('name')->first() ?? \App\Enums\RoleName::GymStaff->value);
@endphp

<div class="space-y-5">
    @if (! $staff)
        <div>
            <label for="existing_user_id" class="panel-label">Select Existing User</label>
            <select id="existing_user_id" name="existing_user_id" class="panel-select">
                <option value="">Create a new staff user</option>
                @foreach ($existingUsers as $existingUser)
                    <option
                        value="{{ $existingUser->id }}"
                        data-name="{{ $existingUser->name }}"
                        data-email="{{ $existingUser->email }}"
                        data-avatar="{{ $existingUser->avatar }}"
                        @if ($hasPhoneColumn) data-phone="{{ $existingUser->phone }}" @endif
                        @selected(old('existing_user_id') == $existingUser->id)
                    >
                        {{ $existingUser->name }} • {{ $existingUser->email }}
                    </option>
                @endforeach
            </select>
            <div id="existing_staff_hint" class="mt-3 hidden rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">
                Existing account selected. Identity fields are reused; only staff scope, role, and permissions are managed here.
            </div>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[1.02fr_0.98fr]">
        <div class="space-y-5">
            <div class="grid gap-5 md:grid-cols-2">
                <div data-existing-account-field>
                    <x-form-input name="name" label="Name" :value="$staff?->name" required />
                </div>
                <div data-existing-account-field>
                    <x-form-input name="email" label="Email" :value="$staff?->email" required />
                </div>
                @if ($hasPhoneColumn)
                    <div data-existing-account-field>
                        <x-form-input name="phone" label="Phone" :value="$staff?->phone" />
                    </div>
                @endif
                <div data-existing-account-field>
                    <x-form-input name="avatar" label="Avatar URL" :value="$staff?->avatar" placeholder="https://..." />
                </div>
                <div class="md:col-span-2" data-existing-account-field>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-4 text-sm text-slate-600 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-300">
                        Staff accounts use Google sign-in. Create or attach the user here, then let them authenticate with their Google account instead of a manual password.
                    </div>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="role" class="panel-label">Role</label>
                    <select id="role" name="role" class="panel-select" required data-staff-role>
                        @if ($allowedRoles === [])
                            <option value="">No assignable roles available in this workspace</option>
                        @endif
                        @foreach ($allowedRoles as $role)
                            <option value="{{ $role }}" @selected($selectedRole === $role)>
                                {{ str($role)->replace('_', ' ')->title() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="panel-label">Status</label>
                    <select id="status" name="status" class="panel-select">
                        <option value="active" @selected(old('status', ($staff?->is_active ?? true) ? 'active' : 'inactive') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', ($staff?->is_active ?? true) ? 'active' : 'inactive') === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between gap-3">
                    <label for="branch_ids" class="panel-label">Assigned Branches</label>
                    <p id="branch_scope_hint" class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $selectedRole === \App\Enums\RoleName::BranchManager->value ? 'Branch managers must have at least one branch.' : 'Gym staff can be branch-scoped or gym-wide.' }}
                    </p>
                </div>
                <select id="branch_ids" name="branch_ids[]" class="panel-select min-h-40" multiple>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(in_array($branch->id, old('branch_ids', $staff?->branches?->pluck('id')->all() ?? []), true))>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="panel-label">Permission Toggles</label>
                <input type="hidden" name="custom_permissions_present" value="1">
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($permissionToggles as $key => $label)
                        @php($allowed = in_array($key, $allowedCustomPermissions, true))
                        <label class="rounded-2xl border {{ $allowed ? 'border-slate-200 bg-slate-50/80 dark:border-white/10 dark:bg-white/[0.03]' : 'border-slate-200 bg-slate-100/80 opacity-60 dark:border-white/5 dark:bg-white/[0.02]' }} px-4 py-4 text-sm text-slate-700 dark:text-slate-200">
                            <div class="flex items-start gap-3">
                                <input type="checkbox" name="custom_permissions[]" value="{{ $key }}" @checked(in_array($key, $selectedPermissions, true)) @disabled(! $allowed)>
                                <span>
                                    <span class="font-semibold text-slate-950 dark:text-white">{{ $label }}</span><br>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $allowed ? 'Can be granted in this scope.' : 'You cannot grant this permission.' }}</span>
                                </span>
                            </div>
                        </label>
                    @endforeach
                </div>
                @if (! $staff && $defaultCustomPermissions !== [])
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Gym staff permission defaults from Settings are preselected for new staff accounts.</p>
                @endif
            </div>

            <div class="panel-card-muted px-4 py-4">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Role Guidance</p>
                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <p>Use <span class="font-medium text-slate-950 dark:text-white">Branch Manager</span> for floor-level operators who should stay constrained to branch scope.</p>
                    <p>Use <span class="font-medium text-slate-950 dark:text-white">Gym Staff</span> for front-desk, billing, or operations users who may work across the gym.</p>
                    <p>Grant only the permissions the role needs; defaults from Settings are meant as a starting point, not the final rule.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const existingUserSelect = document.getElementById('existing_user_id');
            const existingHint = document.getElementById('existing_staff_hint');
            const accountFields = [...document.querySelectorAll('[data-existing-account-field]')];
            const roleSelect = document.querySelector('[data-staff-role]');
            const branchHint = document.getElementById('branch_scope_hint');

            const setRoleHint = () => {
                if (!roleSelect || !branchHint) return;

                branchHint.textContent = roleSelect.value === 'branch_manager'
                    ? 'Branch managers must have at least one branch.'
                    : 'Gym staff can be branch-scoped or gym-wide.';
            };

            const setSectionState = (hidden, disabled = false) => {
                accountFields.forEach((field) => {
                    field.classList.toggle('hidden', hidden);
                    field.querySelectorAll('input, textarea, select').forEach((input) => {
                        input.disabled = disabled;
                    });
                });
            };

            const fillFromSelectedUser = () => {
                if (!existingUserSelect) return;

                const selected = existingUserSelect.selectedOptions[0];
                const hasExistingUser = Boolean(existingUserSelect.value);

                existingHint?.classList.toggle('hidden', !hasExistingUser);
                setSectionState(hasExistingUser, hasExistingUser);

                if (!selected || !hasExistingUser) return;

                const mapping = {
                    name: selected.dataset.name,
                    email: selected.dataset.email,
                    phone: selected.dataset.phone,
                    avatar: selected.dataset.avatar,
                };

                Object.entries(mapping).forEach(([name, value]) => {
                    const input = document.querySelector(`[name="${name}"]`);
                    if (input && value !== undefined) {
                        input.value = value ?? '';
                    }
                });
            };

            existingUserSelect?.addEventListener('change', fillFromSelectedUser);
            roleSelect?.addEventListener('change', setRoleHint);

            fillFromSelectedUser();
            setRoleHint();
        });
    </script>
@endpush
