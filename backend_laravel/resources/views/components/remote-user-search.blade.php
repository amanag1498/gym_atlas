@props([
    'name',
    'label',
    'searchUrl',
    'initialItem' => null,
    'placeholder' => 'Search by name, email, or phone',
    'emptyLabel' => null,
    'branchSource' => null,
    'required' => false,
])

@php
    $fieldId = str_replace(['[', ']'], ['_', ''], $name);
    $selectedId = old($name, data_get($initialItem, 'id'));
    $selectedLabel = $selectedId ? data_get($initialItem, 'label', 'Selected user') : '';
@endphp

<div
    data-remote-user-search
    data-search-url="{{ $searchUrl }}"
    data-branch-source="{{ $branchSource }}"
    class="relative"
>
    <label for="{{ $fieldId }}_search" class="panel-label">{{ $label }}</label>
    <input type="hidden" id="{{ $fieldId }}" name="{{ $name }}" value="{{ $selectedId }}">
    <div class="relative">
        <input
            id="{{ $fieldId }}_search"
            type="search"
            value="{{ $selectedLabel }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            class="panel-input pr-11"
            aria-autocomplete="list"
            aria-expanded="false"
            @if ($required) aria-required="true" @endif
        >
        <button
            type="button"
            data-remote-user-clear
            class="{{ $selectedId ? '' : 'hidden' }} absolute inset-y-0 right-0 flex w-11 items-center justify-center text-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
            aria-label="Clear selection"
        >&times;</button>
    </div>
    <div
        data-remote-user-results
        class="absolute z-40 mt-2 hidden max-h-72 w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900"
        role="listbox"
    ></div>
    <p data-remote-user-help class="mt-2 text-xs text-slate-500 dark:text-slate-400">
        {{ $selectedId ? 'User selected. Clear to search again.' : 'Enter at least 2 characters to search.' }}
    </p>
    @error($name)
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

@push('scripts')
    @once
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const escapeHtml = (value) => {
                    const node = document.createElement('div');
                    node.textContent = value ?? '';
                    return node.innerHTML;
                };

                document.querySelectorAll('[data-remote-user-search]').forEach((root) => {
                    const hidden = root.querySelector('input[type="hidden"]');
                    const search = root.querySelector('input[type="search"]');
                    const results = root.querySelector('[data-remote-user-results]');
                    const clear = root.querySelector('[data-remote-user-clear]');
                    const help = root.querySelector('[data-remote-user-help]');
                    let timer;
                    let controller;

                    const closeResults = () => {
                        results.classList.add('hidden');
                        search.setAttribute('aria-expanded', 'false');
                    };

                    const setSelection = (item) => {
                        hidden.value = item?.id ?? '';
                        search.value = item?.label ?? '';
                        clear.classList.toggle('hidden', !item?.id);
                        help.textContent = item?.id
                            ? (item.description || 'User selected. Clear to search again.')
                            : 'Enter at least 2 characters to search.';
                        closeResults();
                        root.dispatchEvent(new CustomEvent(
                            item?.id ? 'remote-user-selected' : 'remote-user-cleared',
                            { detail: item || {}, bubbles: true }
                        ));
                    };

                    const showMessage = (message) => {
                        results.innerHTML = `<div class="px-3 py-3 text-sm text-slate-500 dark:text-slate-400">${escapeHtml(message)}</div>`;
                        results.classList.remove('hidden');
                        search.setAttribute('aria-expanded', 'true');
                    };

                    const searchUsers = async () => {
                        const query = search.value.trim();
                        if (query.length < 2) {
                            closeResults();
                            return;
                        }

                        controller?.abort();
                        controller = new AbortController();
                        showMessage('Searching...');

                        const url = new URL(root.dataset.searchUrl, window.location.origin);
                        url.searchParams.set('q', query);
                        const branchSource = root.dataset.branchSource;
                        const branchId = branchSource ? document.getElementById(branchSource)?.value : '';
                        if (branchId) {
                            url.searchParams.set('branch_id', branchId);
                        }

                        try {
                            const response = await fetch(url, {
                                headers: { 'Accept': 'application/json' },
                                signal: controller.signal,
                            });
                            if (!response.ok) throw new Error('Search failed');

                            const payload = await response.json();
                            const items = payload.data || [];
                            if (!items.length) {
                                showMessage('No matching users found.');
                                return;
                            }

                            results.innerHTML = items.map((item, index) => `
                                <button type="button" data-result-index="${index}" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-slate-100 dark:hover:bg-slate-800" role="option">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">${escapeHtml((item.label || '?').charAt(0).toUpperCase())}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">${escapeHtml(item.label)}</span>
                                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">${escapeHtml(item.description || '')}</span>
                                    </span>
                                </button>
                            `).join('');
                            results.classList.remove('hidden');
                            search.setAttribute('aria-expanded', 'true');
                            results.querySelectorAll('[data-result-index]').forEach((button) => {
                                button.addEventListener('click', () => setSelection(items[Number(button.dataset.resultIndex)]));
                            });
                        } catch (error) {
                            if (error.name !== 'AbortError') showMessage('Unable to search right now.');
                        }
                    };

                    search.addEventListener('input', () => {
                        if (hidden.value) {
                            hidden.value = '';
                            clear.classList.add('hidden');
                            root.dispatchEvent(new CustomEvent('remote-user-cleared', { bubbles: true }));
                        }
                        window.clearTimeout(timer);
                        timer = window.setTimeout(searchUsers, 250);
                    });
                    search.addEventListener('focus', () => {
                        if (search.value.trim().length >= 2 && !hidden.value) searchUsers();
                    });
                    search.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') closeResults();
                    });
                    clear.addEventListener('click', () => {
                        setSelection(null);
                        search.focus();
                    });
                    root.addEventListener('remote-user-set', (event) => setSelection(event.detail));
                    document.addEventListener('click', (event) => {
                        if (!root.contains(event.target)) closeResults();
                    });
                });
            });
        </script>
    @endonce
@endpush
