@props([
    'id',
    'name',
    'label' => 'Operating Hours',
    'value' => [],
    'helper' => 'Add one or more time windows for each day. Leave a day closed when needed.',
])

<div
    data-operating-hours-editor
    data-input-id="{{ $id }}"
    data-initial='@json($value)'
    class="space-y-4"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <label for="{{ $id }}" class="panel-label">{{ $label }}</label>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $helper }}</p>
        </div>
        <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
            Multi-slot
        </div>
    </div>

    <textarea id="{{ $id }}" name="{{ $name }}" class="hidden" aria-hidden="true">@json($value)</textarea>

    <div class="space-y-3" data-operating-hours-days></div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

                const normalize = (value) => {
                    const payload = value && typeof value === 'object' ? value : {};

                    return Object.fromEntries(days.map((day) => {
                        const dayValue = payload[day];
                        const slots = Array.isArray(dayValue)
                            ? dayValue
                            : (dayValue && typeof dayValue === 'object' && ('open' in dayValue || 'close' in dayValue) ? [dayValue] : []);

                        const cleanSlots = slots
                            .map((slot) => ({
                                open: typeof slot?.open === 'string' ? slot.open : '',
                                close: typeof slot?.close === 'string' ? slot.close : '',
                            }))
                            .filter((slot) => slot.open || slot.close);

                        return [day, cleanSlots];
                    }));
                };

                const dayLabel = (day) => day.replace(/^\w/, (char) => char.toUpperCase());

                document.querySelectorAll('[data-operating-hours-editor]').forEach((editor) => {
                    const input = document.getElementById(editor.dataset.inputId);
                    const daysContainer = editor.querySelector('[data-operating-hours-days]');
                    let state;

                    try {
                        state = normalize(JSON.parse(editor.dataset.initial || '{}'));
                    } catch (error) {
                        state = normalize({});
                    }

                    const sync = () => {
                        input.value = JSON.stringify(state);
                    };

                    const addSlot = (day) => {
                        state[day].push({ open: '', close: '' });
                        render();
                    };

                    const removeSlot = (day, index) => {
                        state[day].splice(index, 1);
                        render();
                    };

                    const setClosed = (day, closed) => {
                        state[day] = closed ? [] : [{ open: '', close: '' }];
                        render();
                    };

                    const render = () => {
                        sync();
                        daysContainer.innerHTML = '';

                        days.forEach((day) => {
                            const row = document.createElement('div');
                            row.className = 'rounded-[1.35rem] border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-950/70 dark:shadow-black/20';

                            const slots = state[day] ?? [];
                            const closed = slots.length === 0;

                            row.innerHTML = `
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-[140px]">
                                        <div class="text-sm font-semibold text-slate-950 dark:text-slate-100">${dayLabel(day)}</div>
                                        <label class="mt-2 inline-flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                                            <input type="checkbox" class="rounded border-slate-300 text-brand-500 focus:ring-brand-500/20 dark:border-slate-600 dark:bg-slate-900" ${closed ? 'checked' : ''} data-day-closed="${day}">
                                            Closed
                                        </label>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="space-y-3" data-day-slots="${day}"></div>
                                        <button type="button" class="mt-3 inline-flex items-center rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-brand-200 hover:text-brand-600 dark:border-slate-700 dark:text-slate-300 dark:hover:border-brand-500/40 dark:hover:text-brand-300 ${closed ? 'hidden' : ''}" data-add-slot="${day}">
                                            Add time window
                                        </button>
                                    </div>
                                </div>
                            `;

                            const slotsContainer = row.querySelector(`[data-day-slots="${day}"]`);

                            if (!closed) {
                                slots.forEach((slot, index) => {
                                    const slotRow = document.createElement('div');
                                    slotRow.className = 'grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]';
                                    slotRow.innerHTML = `
                                        <label class="block">
                                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Open</span>
                                            <input type="time" value="${slot.open}" class="panel-input" data-slot-open="${day}:${index}">
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Close</span>
                                            <input type="time" value="${slot.close}" class="panel-input" data-slot-close="${day}:${index}">
                                        </label>
                                        <div class="flex items-end">
                                            <button type="button" class="inline-flex h-11 items-center rounded-2xl border border-rose-200 px-3 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/25 dark:text-rose-300 dark:hover:bg-rose-500/10" data-remove-slot="${day}:${index}">
                                                Remove
                                            </button>
                                        </div>
                                    `;

                                    slotsContainer.appendChild(slotRow);
                                });
                            } else {
                                const closedState = document.createElement('div');
                                closedState.className = 'rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400';
                                closedState.textContent = 'No operating windows for this day.';
                                slotsContainer.appendChild(closedState);
                            }

                            daysContainer.appendChild(row);
                        });
                    };

                    editor.addEventListener('click', (event) => {
                        const addButton = event.target.closest('[data-add-slot]');
                        const removeButton = event.target.closest('[data-remove-slot]');

                        if (addButton) {
                            addSlot(addButton.dataset.addSlot);
                        }

                        if (removeButton) {
                            const [day, index] = removeButton.dataset.removeSlot.split(':');
                            removeSlot(day, Number(index));
                        }
                    });

                    editor.addEventListener('change', (event) => {
                        const closedToggle = event.target.closest('[data-day-closed]');
                        const openInput = event.target.closest('[data-slot-open]');
                        const closeInput = event.target.closest('[data-slot-close]');

                        if (closedToggle) {
                            setClosed(closedToggle.dataset.dayClosed, closedToggle.checked);
                            return;
                        }

                        if (openInput) {
                            const [day, index] = openInput.dataset.slotOpen.split(':');
                            state[day][Number(index)].open = openInput.value;
                            sync();
                        }

                        if (closeInput) {
                            const [day, index] = closeInput.dataset.slotClose.split(':');
                            state[day][Number(index)].close = closeInput.value;
                            sync();
                        }
                    });

                    render();
                });
            })();
        </script>
    @endpush
@endonce
