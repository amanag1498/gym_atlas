@props([
    'name' => 'profile_photo',
    'label' => 'Profile Photo',
    'currentUrl' => null,
])

<div data-profile-photo-upload>
    <label for="{{ $name }}" class="panel-label">{{ $label }}</label>
    <div class="flex items-center gap-4">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800">
            <img
                data-profile-photo-preview
                src="{{ $currentUrl }}"
                alt=""
                class="{{ $currentUrl ? '' : 'hidden' }} h-full w-full object-cover"
            >
            <span data-profile-photo-placeholder class="{{ $currentUrl ? 'hidden' : '' }} text-xs font-semibold text-slate-400">Photo</span>
        </div>
        <div class="min-w-0 flex-1">
            <input
                id="{{ $name }}"
                name="{{ $name }}"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                class="panel-input file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold dark:file:bg-slate-800"
            >
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">JPG, PNG, or WebP. Maximum 4 MB.</p>
            @error($name)
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

@push('scripts')
    @once
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-profile-photo-upload]').forEach((root) => {
                    const input = root.querySelector('input[type="file"]');
                    const preview = root.querySelector('[data-profile-photo-preview]');
                    const placeholder = root.querySelector('[data-profile-photo-placeholder]');

                    input?.addEventListener('change', () => {
                        const file = input.files?.[0];
                        if (!file || !preview) return;

                        preview.src = URL.createObjectURL(file);
                        preview.classList.remove('hidden');
                        placeholder?.classList.add('hidden');
                    });
                });
            });
        </script>
    @endonce
@endpush
