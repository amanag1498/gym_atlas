@props([
    'recipients' => collect(),
    'rows' => [],
    'defaultRecipientId' => null,
    'branchSource' => null,
    'variant' => 'light',
    'maxRows' => 10,
])

@php
    $builderId = 'commission-builder-'.str()->uuid();
    $submittedRows = old('commissions');
    $initialRows = collect($submittedRows !== null ? $submittedRows : $rows)->values();
    if ($initialRows->isEmpty()) {
        $initialRows = collect([[]]);
    }
    $rowClass = $variant === 'dark'
        ? 'border-white/10 bg-slate-950/35'
        : 'border-white bg-white/80 dark:border-slate-800 dark:bg-slate-950/60';
@endphp

<div id="{{ $builderId }}" data-commission-builder data-max-rows="{{ $maxRows }}" @if($branchSource) data-branch-source="{{ $branchSource }}" @endif>
    @if($recipients->isEmpty())
        <p class="rounded-xl bg-white/80 px-4 py-3 text-sm text-slate-600 dark:bg-slate-950/60 dark:text-slate-300">No active trainers or staff are available. Add the team member first, then return here.</p>
    @else
        <div class="space-y-3" data-commission-rows>
            @foreach($initialRows as $index => $entry)
                @php
                    $recipientId = data_get($entry, 'recipient_user_id', $index === 0 ? $defaultRecipientId : null);
                    $category = data_get($entry, 'category', $index === 0 ? 'pt' : 'sales');
                    $calculationType = data_get($entry, 'calculation_type', 'percentage');
                    $value = data_get($entry, 'value', '');
                    $recurrence = data_get($entry, 'recurrence', $index === 0 ? 'recurring' : 'one_time');
                @endphp
                <div class="grid gap-3 rounded-xl border p-3 lg:grid-cols-[1.4fr_0.75fr_0.8fr_0.65fr_0.9fr_auto] {{ $rowClass }}" data-commission-row>
                    <div>
                        <label class="panel-label" for="{{ $builderId }}-recipient-{{ $index }}">Recipient</label>
                        <select id="{{ $builderId }}-recipient-{{ $index }}" name="commissions[{{ $index }}][recipient_user_id]" class="panel-select" data-commission-recipient>
                            <option value="">Select trainer or staff</option>
                            @foreach($recipients as $recipient)
                                <option value="{{ $recipient->id }}" data-branch-ids="{{ implode(',', $recipient->getAttribute('compensation_branch_ids') ?? []) }}" @selected((int)$recipientId === $recipient->id)>{{ $recipient->name }} · {{ ucfirst($recipient->getAttribute('compensation_role')) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="panel-label">For</label><select name="commissions[{{ $index }}][category]" class="panel-select"><option value="pt" @selected($category === 'pt')>PT</option><option value="sales" @selected($category === 'sales')>Sale</option></select></div>
                    <div><label class="panel-label">Rule</label><select name="commissions[{{ $index }}][calculation_type]" class="panel-select"><option value="percentage" @selected($calculationType === 'percentage')>Percentage</option><option value="fixed" @selected($calculationType === 'fixed')>Fixed ₹</option></select></div>
                    <div><label class="panel-label">Value</label><input name="commissions[{{ $index }}][value]" type="number" min="0" step="0.01" value="{{ $value }}" class="panel-input" placeholder="0"></div>
                    <div><label class="panel-label">Cycle</label><select name="commissions[{{ $index }}][recurrence]" class="panel-select"><option value="recurring" @selected($recurrence === 'recurring')>Every renewal</option><option value="one_time" @selected($recurrence === 'one_time')>First cycle only</option></select></div>
                    <div class="flex items-end"><button type="button" class="panel-btn-secondary !px-3 !py-2.5" data-remove-commission-row aria-label="Remove commission recipient">Remove</button></div>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500 dark:text-slate-400"><span data-commission-count>{{ $initialRows->count() }}</span> of {{ $maxRows }} recipients added</p>
            <button type="button" class="panel-btn-secondary" data-add-commission-row>+ Add recipient</button>
        </div>
        <p class="mt-2 hidden text-xs font-medium text-amber-700 dark:text-amber-300" data-commission-branch-hint>A recipient was cleared because they are not assigned to the selected branch.</p>

        <template data-commission-template>
            <div class="grid gap-3 rounded-xl border p-3 lg:grid-cols-[1.4fr_0.75fr_0.8fr_0.65fr_0.9fr_auto] {{ $rowClass }}" data-commission-row>
                <div>
                    <label class="panel-label" for="{{ $builderId }}-recipient-__INDEX__">Recipient</label>
                    <select id="{{ $builderId }}-recipient-__INDEX__" name="commissions[__INDEX__][recipient_user_id]" class="panel-select" data-commission-recipient>
                        <option value="">Select trainer or staff</option>
                        @foreach($recipients as $recipient)
                            <option value="{{ $recipient->id }}" data-branch-ids="{{ implode(',', $recipient->getAttribute('compensation_branch_ids') ?? []) }}">{{ $recipient->name }} · {{ ucfirst($recipient->getAttribute('compensation_role')) }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="panel-label">For</label><select name="commissions[__INDEX__][category]" class="panel-select"><option value="pt">PT</option><option value="sales">Sale</option></select></div>
                <div><label class="panel-label">Rule</label><select name="commissions[__INDEX__][calculation_type]" class="panel-select"><option value="percentage">Percentage</option><option value="fixed">Fixed ₹</option></select></div>
                <div><label class="panel-label">Value</label><input name="commissions[__INDEX__][value]" type="number" min="0" step="0.01" class="panel-input" placeholder="0"></div>
                <div><label class="panel-label">Cycle</label><select name="commissions[__INDEX__][recurrence]" class="panel-select"><option value="recurring">Every renewal</option><option value="one_time">First cycle only</option></select></div>
                <div class="flex items-end"><button type="button" class="panel-btn-secondary !px-3 !py-2.5" data-remove-commission-row aria-label="Remove commission recipient">Remove</button></div>
            </div>
        </template>
    @endif
</div>

@if($recipients->isNotEmpty())
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const builder = document.getElementById(@js($builderId));
                if (!builder) return;
                const rows = builder.querySelector('[data-commission-rows]');
                const template = builder.querySelector('[data-commission-template]');
                const addButton = builder.querySelector('[data-add-commission-row]');
                const count = builder.querySelector('[data-commission-count]');
                const branchHint = builder.querySelector('[data-commission-branch-hint]');
                const maxRows = Number(builder.dataset.maxRows || 10);
                const branchSource = builder.dataset.branchSource;
                const branchSelect = branchSource ? document.getElementById(branchSource) : null;
                let nextIndex = rows.querySelectorAll('[data-commission-row]').length;

                const filterRecipients = () => {
                    if (!branchSelect) return;
                    const branchId = branchSelect.value;
                    let cleared = false;
                    rows.querySelectorAll('[data-commission-recipient]').forEach((select) => {
                        Array.from(select.options).forEach((option) => {
                            if (!option.value) return;
                            const branchIds = (option.dataset.branchIds || '').split(',').filter(Boolean);
                            const visible = !branchId || branchIds.length === 0 || branchIds.includes(branchId);
                            option.hidden = !visible;
                            if (option.selected && !visible) {
                                select.value = '';
                                cleared = true;
                            }
                        });
                    });
                    branchHint?.classList.toggle('hidden', !cleared);
                };

                const refresh = () => {
                    const rowCount = rows.querySelectorAll('[data-commission-row]').length;
                    if (count) count.textContent = String(rowCount);
                    if (addButton) addButton.disabled = rowCount >= maxRows;
                    rows.querySelectorAll('[data-remove-commission-row]').forEach((button) => {
                        button.classList.toggle('invisible', rowCount === 1);
                    });
                    filterRecipients();
                };

                addButton?.addEventListener('click', () => {
                    if (rows.querySelectorAll('[data-commission-row]').length >= maxRows) return;
                    rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
                    refresh();
                });
                rows.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-remove-commission-row]');
                    if (!removeButton || rows.querySelectorAll('[data-commission-row]').length === 1) return;
                    removeButton.closest('[data-commission-row]')?.remove();
                    refresh();
                });
                branchSelect?.addEventListener('change', filterRecipients);
                refresh();
            });
        </script>
    @endpush
@endif
