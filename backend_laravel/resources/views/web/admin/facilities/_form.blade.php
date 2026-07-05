@php($isEdit = $facility->exists)

<x-premium-card class="overflow-hidden">
    <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="max-w-3xl">
                <div class="panel-toolbar-chip">{{ $isEdit ? 'Facility Update' : 'Facility Master' }}</div>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $isEdit ? 'Edit Facility' : 'Create Facility' }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $isEdit ? 'Update the shared facility name, presentation metadata, and lifecycle status for platform-wide gym usage.' : 'Create a reusable platform facility that can be attached to gyms and branches throughout the ecosystem.' }}</p>
            </div>
            <div class="admin-detail-grid-compact w-full xl:max-w-md">
                @if ($isEdit)
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Current State</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <x-status-badge :label="$facility->is_active ? 'Active' : 'Inactive'" :tone="$facility->is_active ? 'success' : 'danger'" />
                            <x-status-badge :label="$facility->status ?: 'active'" tone="neutral" />
                        </div>
                    </div>
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Usage Footprint</div>
                        <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ ($facility->gyms_count ?? 0) + ($facility->branches_count ?? 0) }}</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $facility->gyms_count ?? 0 }} gyms • {{ $facility->branches_count ?? 0 }} branches</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <form method="POST" action="{{ $isEdit ? route('web.admin.facilities.update', $facility) : route('web.admin.facilities.store') }}" class="p-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
            <div class="space-y-6">
                <div class="admin-detail-grid">
                    <x-form-input name="name" label="Facility Name" :value="old('name', $facility->name)" placeholder="Strength Zone" required />
                    <x-form-input name="icon" label="Icon" :value="old('icon', $facility->icon)" placeholder="dumbbell" />
                    <x-form-select
                        name="status"
                        label="Status"
                        :selected="old('status', $facility->status ?: ($facility->is_active ? 'active' : 'inactive'))"
                        :options="['active' => 'Active', 'inactive' => 'Inactive']"
                    />
                </div>

                <div>
                    <label class="panel-label" for="description">Description</label>
                    <textarea id="description" name="description" class="panel-textarea" rows="5" placeholder="Free weights, racks, platforms, and heavy lifting support.">{{ old('description', $facility->description) }}</textarea>
                    @error('description') <div class="mt-2 text-sm text-rose-500">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="space-y-4">
                <div class="panel-card-muted px-4 py-4">
                    <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Usage Guidance</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Active facilities appear in gym create/edit forms and listing setup. Deactivate instead of deleting when this facility is already attached to live gyms or branches.</p>
                </div>

                <div class="panel-card-muted px-4 py-4">
                    <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Preview</h3>
                    <div class="mt-3 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-4 dark:border-slate-800 dark:bg-slate-900/80">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-semibold text-slate-950 dark:text-white">{{ old('name', $facility->name ?: 'Facility Name') }}</div>
                            <x-status-badge :label="str(old('status', $facility->status ?: 'active'))->title()" tone="info" />
                        </div>
                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">Icon {{ old('icon', $facility->icon ?: 'not set') }}</div>
                        <div class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ old('description', $facility->description ?: 'Description will help operators understand where this facility should be used.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <x-action-button type="submit">{{ $isEdit ? 'Update Facility' : 'Create Facility' }}</x-action-button>
            <x-action-button as="a" href="{{ route('web.admin.facilities.index') }}" variant="secondary">Back to Facilities</x-action-button>
        </div>
    </form>
</x-premium-card>
