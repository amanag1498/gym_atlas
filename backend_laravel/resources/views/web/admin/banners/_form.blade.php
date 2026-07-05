@php($isEdit = $banner->exists)

<div class="space-y-6">
    <section class="panel-hero">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <div class="panel-toolbar-chip">Campaign Placement</div>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $isEdit ? 'Edit Banner' : 'Create Banner' }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Build compact app banners with cleaner ordering, tighter metadata, and theme-aware visual preview states.</p>
            </div>
            @if ($isEdit)
                <div class="admin-detail-grid-compact w-full xl:max-w-sm">
                    <div class="panel-card-muted px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Status</div>
                        <div class="mt-2">
                            <x-status-badge :label="$banner->is_active ? 'Active' : 'Inactive'" :tone="$banner->is_active ? 'success' : 'danger'" />
                        </div>
                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">Updated {{ optional($banner->updated_at)->diffForHumans() ?: 'just now' }}</div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <form method="POST" action="{{ $isEdit ? route('web.admin.banners.update', $banner) : route('web.admin.banners.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_360px]">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-premium-card class="p-5">
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-form-input name="title" label="Title" :value="old('title', $banner->title)" placeholder="Summer transformation challenge" required />
                </div>
                <div class="md:col-span-2">
                    <x-form-input name="image_url" label="Image URL" :value="old('image_url', $banner->image_url)" placeholder="https://cdn.example.com/banner.jpg" />
                </div>
                <div class="md:col-span-2">
                    <x-form-input name="link_url" label="Destination URL" :value="old('link_url', $banner->link_url)" placeholder="https://example.com/campaign" />
                </div>
                <div>
                    <x-form-input type="number" name="sort_order" label="Sort Order" :value="old('sort_order', $banner->sort_order ?? 0)" placeholder="0" min="0" />
                </div>
                <div>
                    <label class="panel-label">Visibility</label>
                    <label class="panel-card-muted flex items-start gap-3 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="mt-1" name="is_active" value="1" @checked(old('is_active', $banner->is_active ?? true))>
                        <span>
                            <span class="block font-semibold text-slate-900 dark:text-white">Show banner in app</span>
                            <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Keep drafts hidden until the creative and destination are ready.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <x-action-button type="submit">{{ $isEdit ? 'Update Banner' : 'Create Banner' }}</x-action-button>
                <x-action-button as="a" href="{{ route('web.admin.banners.index') }}" variant="secondary">Back to Banners</x-action-button>
            </div>
        </x-premium-card>

        <div class="space-y-6">
            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Preview</h3>
                <p class="panel-section-copy">Use this as a quick confidence check before publishing the placement live.</p>
                <div class="mt-4 rounded-[24px] border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="overflow-hidden rounded-[18px] border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <div class="aspect-[16/7] overflow-hidden bg-slate-100 dark:bg-slate-900">
                            @if (old('image_url', $banner->image_url))
                                <img src="{{ old('image_url', $banner->image_url) }}" alt="{{ old('title', $banner->title) ?: 'Banner preview' }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full items-center justify-center text-xs font-medium uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">Artwork preview</div>
                            @endif
                        </div>
                        <div class="space-y-2 px-4 py-4">
                            <div class="text-sm font-semibold text-slate-950 dark:text-white">{{ old('title', $banner->title) ?: 'Banner title' }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ old('link_url', $banner->link_url) ?: 'Destination URL appears here' }}</div>
                        </div>
                    </div>
                </div>
            </x-premium-card>

            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Publishing Notes</h3>
                <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    <div class="panel-card-muted px-4 py-3">Use a stable CDN URL so the app does not render a broken hero state.</div>
                    <div class="panel-card-muted px-4 py-3">Lower sort orders appear first; reserve gaps if marketing rotates campaigns often.</div>
                    <div class="panel-card-muted px-4 py-3">Keep banner copy short because the visual area should remain dominant.</div>
                </div>
            </x-premium-card>
        </div>
    </form>
</div>
