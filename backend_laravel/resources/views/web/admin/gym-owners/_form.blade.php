<div class="space-y-6">
    <section class="panel-hero">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">{{ $isEdit ? 'Update gym owner profile' : 'Create gym owner account' }}</span>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $isEdit ? 'Edit Gym Owner' : 'Add Gym Owner' }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $isEdit ? 'Update the owner profile and keep role-based access intact.' : 'Create a new gym owner account that can later be assigned to gyms from platform admin.' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-owners.index') }}">Back To Owners</x-action-button>
                @if ($isEdit)
                    <x-action-button as="a" href="{{ route('web.admin.gym-owners.show', $owner) }}">View Profile</x-action-button>
                @endif
            </div>
        </div>
    </section>

    <form method="POST" action="{{ $isEdit ? route('web.admin.gym-owners.update', $owner) : route('web.admin.gym-owners.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.75fr)]">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-premium-card class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="panel-section-title">Owner Profile</h3>
                    <p class="panel-section-copy">Basic account details and contact information for the gym owner.</p>
                </div>
                <x-status-badge :label="$isEdit ? ($owner->is_active ? 'Active' : 'Inactive') : 'Gym Owner Role'" :tone="$isEdit ? ($owner->is_active ? 'success' : 'danger') : 'verified'" />
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <x-form-input name="name" label="Full Name" :value="old('name', $owner->name)" placeholder="Owner full name" required />
                </div>
                <div>
                    <x-form-input type="email" name="email" label="Email" :value="old('email', $owner->email)" placeholder="owner@example.com" required />
                </div>
                @if ($hasPhoneColumn)
                    <div>
                        <x-form-input name="phone" label="Phone" :value="old('phone', $owner->phone)" placeholder="+91..." />
                    </div>
                @endif
            </div>
        </x-premium-card>

        <div class="space-y-6">
            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Submit</h3>
                <p class="panel-section-copy">{{ $isEdit ? 'Save profile changes and keep the owner linked to current gyms.' : 'A temporary password will be generated and shown once after creation.' }}</p>
                <div class="mt-4 grid gap-3">
                    <x-action-button type="submit">{{ $isEdit ? 'Update Owner' : 'Create Owner' }}</x-action-button>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-owners.index') }}">Cancel</x-action-button>
                </div>
            </x-premium-card>

            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Access Scope</h3>
                <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Gym owners can be assigned one or more gyms, open the gym workspace, and then operate trainers, members, billing, listings, and branch-level workflows from there.</p>
            </x-premium-card>
        </div>
    </form>
</div>
