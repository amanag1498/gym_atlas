@php($isEdit = $trainerSpecialization->exists)

<div class="space-y-6">
    <section class="panel-hero">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <div class="panel-toolbar-chip">{{ $isEdit ? 'Catalog Update' : 'Trainer Catalog' }}</div>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $isEdit ? 'Edit Trainer Specialization' : 'Create Trainer Specialization' }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $isEdit ? 'Update the trainer-facing label, description, order, and lifecycle status.' : 'Create a reusable trainer specialization for onboarding and profile setup.' }}</p>
            </div>
            @if ($isEdit)
                <div class="admin-detail-grid-compact w-full xl:max-w-xl">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current Status</div>
                        <div class="mt-2">
                            <x-status-badge :label="$trainerSpecialization->is_active ? 'Active' : 'Inactive'" :tone="$trainerSpecialization->is_active ? 'success' : 'danger'" />
                        </div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Assigned Trainers</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ $trainerSpecialization->trainer_profiles_count ?? 0 }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">trainer profiles linked to this specialization</div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <form method="POST" action="{{ $isEdit ? route('web.admin.trainer-specializations.update', $trainerSpecialization) : route('web.admin.trainer-specializations.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_340px]">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-premium-card class="p-5">
            <div class="grid gap-5 md:grid-cols-2">
                <x-form-input name="name" label="Specialization Name" :value="old('name', $trainerSpecialization->name)" placeholder="Strength" required />
                <x-form-input name="icon" label="Icon" :value="old('icon', $trainerSpecialization->icon)" placeholder="fitness_center" />
                <x-form-input name="sort_order" label="Sort Order" :value="old('sort_order', $trainerSpecialization->sort_order)" type="number" min="0" />
                <x-form-select name="status" label="Status" :selected="old('status', $trainerSpecialization->status ?: ($trainerSpecialization->is_active ? 'active' : 'inactive'))" :options="['active' => 'Active', 'inactive' => 'Inactive']" />
                <div class="md:col-span-2">
                    <label class="panel-label" for="description">Description</label>
                    <textarea id="description" name="description" class="panel-textarea" rows="5" placeholder="Strength training, progressive overload, and technique coaching.">{{ old('description', $trainerSpecialization->description) }}</textarea>
                    @error('description') <div class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <x-action-button type="submit">{{ $isEdit ? 'Update Specialization' : 'Create Specialization' }}</x-action-button>
                <x-action-button as="a" href="{{ route('web.admin.trainer-specializations.index') }}" variant="secondary">Back to Specializations</x-action-button>
            </div>
        </x-premium-card>

        <div class="space-y-6">
            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Trainer App Usage</h3>
                <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    <div class="panel-card-muted px-4 py-3">Active specializations appear in trainer onboarding as selectable coaching focus areas.</div>
                    <div class="panel-card-muted px-4 py-3">Sort order controls how these options feel in setup, so keep the sequence deliberate.</div>
                    <div class="panel-card-muted px-4 py-3">Deactivate instead of deleting when trainers already use this specialization.</div>
                </div>
            </x-premium-card>

            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Preview</h3>
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="flex items-start justify-between gap-3">
                        <div class="font-semibold text-slate-950 dark:text-white">{{ old('name', $trainerSpecialization->name ?: 'Specialization Name') }}</div>
                        <x-status-badge :label="str(old('status', $trainerSpecialization->status ?: 'active'))->title()" tone="info" />
                    </div>
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">Icon {{ old('icon', $trainerSpecialization->icon ?: 'not set') }} • Sort order {{ old('sort_order', $trainerSpecialization->sort_order ?? 0) }}</div>
                    <div class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ old('description', $trainerSpecialization->description ?: 'Trainer-facing description will appear here.') }}</div>
                </div>
            </x-premium-card>
        </div>
    </form>
</div>
