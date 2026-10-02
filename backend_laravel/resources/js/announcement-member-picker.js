export const initializeAnnouncementMemberPicker = () => {
    const form = document.querySelector('[data-announcement-composer]');
    if (!form) return;

    const audience = form.querySelector('[data-gym-announcement-audience]');
    const branchField = form.querySelector('[data-gym-announcement-branch]');
    const branch = form.querySelector('#announcement_branch_id');
    const membersField = form.querySelector('[data-gym-announcement-members]');
    const chips = form.querySelector('[data-selected-member-chips]');
    const hiddenInputs = form.querySelector('[data-selected-member-inputs]');
    const emptySelection = form.querySelector('[data-member-empty]');
    const count = form.querySelector('[data-member-count]');
    const summary = form.querySelector('[data-audience-summary]');
    const dialog = form.querySelector('#announcement-member-picker');
    const search = form.querySelector('[data-member-search]');
    const results = form.querySelector('[data-member-results]');
    const loadMore = form.querySelector('[data-member-load-more]');
    const dialogCount = form.querySelector('[data-member-dialog-count]');
    const dialogScope = form.querySelector('[data-member-picker-scope]');
    if (!audience || !branch || !dialog || !search || !results) return;

    const selected = new Map(Array.from(chips.querySelectorAll('[data-member-chip]'), (chip) => [
        String(chip.dataset.memberId),
        { id: String(chip.dataset.memberId), name: chip.dataset.memberName, email: chip.dataset.memberEmail },
    ]));
    let searchTimer;
    let request;
    let nextPage = null;
    let loading = false;

    const element = (tag, className, content = '') => {
        const node = document.createElement(tag);
        node.className = className;
        node.textContent = content;
        return node;
    };

    const showResultMessage = (message) => {
        results.replaceChildren(element('p', 'py-5 text-center text-sm text-slate-600 dark:text-slate-400', message));
    };

    const refreshResultStates = () => {
        results.querySelectorAll('[data-result-member-id]').forEach((button) => {
            const isSelected = selected.has(button.dataset.resultMemberId);
            button.setAttribute('aria-pressed', String(isSelected));
            button.classList.toggle('border-brand-400', isSelected);
            button.classList.toggle('bg-brand-50', isSelected);
            button.classList.toggle('dark:bg-brand-500/10', isSelected);
            button.querySelector('[data-selection-mark]').textContent = isSelected ? 'Selected' : 'Select';
        });
    };

    const updateSummary = () => {
        const branchName = branch.selectedOptions[0]?.textContent || 'this branch';
        const amount = selected.size;
        summary.textContent = audience.value === 'gym_wide'
            ? 'This update will be sent to all eligible gym members.'
            : audience.value === 'branch_specific'
                ? `This update will be sent to eligible members at ${branch.value ? branchName : 'the selected branch'}.`
                : `${amount} ${amount === 1 ? 'member' : 'members'} selected${branch.value ? ` in ${branchName}` : ' across eligible branches'}.`;
    };

    const renderSelection = () => {
        chips.replaceChildren();
        hiddenInputs.replaceChildren();
        selected.forEach((member, id) => {
            const chip = element('span', 'inline-flex max-w-full items-center gap-2 rounded-full border border-brand-200 bg-white py-1 pl-3 pr-1 text-xs font-medium text-slate-800 dark:border-brand-500/30 dark:bg-slate-900 dark:text-slate-100');
            chip.append(element('span', 'truncate', member.name || 'Member'));
            const remove = element('button', 'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-slate-300 dark:hover:bg-slate-700', '×');
            remove.type = 'button';
            remove.setAttribute('aria-label', `Remove ${member.name || 'member'}`);
            remove.addEventListener('click', () => {
                selected.delete(id);
                renderSelection();
            });
            chip.append(remove);
            chips.append(chip);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'member_ids[]';
            input.value = id;
            input.disabled = audience.value !== 'selected_members';
            hiddenInputs.append(input);
        });
        const label = `${selected.size} selected`;
        count.textContent = label;
        dialogCount.textContent = label;
        emptySelection.classList.toggle('hidden', selected.size > 0);
        refreshResultStates();
        updateSummary();
    };

    const addResults = (items) => {
        items.forEach((member) => {
            const id = String(member.id);
            const button = element('button', 'flex min-h-14 w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-brand-300 hover:bg-brand-50/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700');
            button.type = 'button';
            button.dataset.resultMemberId = id;
            const avatar = element('span', 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300', (member.name || '?').slice(0, 1).toUpperCase());
            const details = element('span', 'min-w-0 flex-1');
            details.append(element('span', 'block truncate text-sm font-semibold text-slate-950 dark:text-slate-100', member.name || 'Member'));
            details.append(element('span', 'block truncate text-xs text-slate-600 dark:text-slate-400', member.email || 'No email'));
            button.append(avatar, details, element('span', 'shrink-0 text-xs font-semibold text-brand-700 dark:text-brand-300', 'Select'));
            button.lastElementChild.dataset.selectionMark = '';
            button.addEventListener('click', () => {
                if (selected.has(id)) selected.delete(id);
                else selected.set(id, { id, name: member.name, email: member.email });
                renderSelection();
            });
            results.append(button);
        });
        refreshResultStates();
    };

    const searchMembers = async (page = 1) => {
        const query = search.value.trim();
        if (query.length === 1) {
            request?.abort();
            nextPage = null;
            loadMore.classList.add('hidden');
            showResultMessage('Type one more character to search, or clear the field to browse members.');
            return;
        }
        if (loading && page > 1) return;
        request?.abort();
        const controller = new AbortController();
        request = controller;
        loading = true;
        loadMore.disabled = true;
        if (page === 1) {
            nextPage = null;
            loadMore.classList.add('hidden');
            showResultMessage('Searching members…');
        }
        else loadMore.textContent = 'Loading…';

        const url = new URL(form.dataset.memberSearchUrl, window.location.origin);
        if (query) url.searchParams.set('q', query);
        url.searchParams.set('page', String(page));
        if (branch.value) url.searchParams.set('branch_id', branch.value);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Member search failed');
            const payload = await response.json();
            if (request !== controller) return;
            if (page === 1) results.replaceChildren();
            addResults(payload.data || []);
            if (!results.children.length) showResultMessage('No matching members. Try another name or email.');
            nextPage = payload.next_page;
            loadMore.classList.toggle('hidden', !nextPage);
        } catch (error) {
            if (error.name !== 'AbortError' && request === controller) {
                if (page === 1) showResultMessage('Could not load members. Check your connection and try again.');
                else loadMore.textContent = 'Could not load more. Tap to retry.';
            }
        } finally {
            if (request === controller) {
                loading = false;
                loadMore.disabled = false;
                if (loadMore.textContent === 'Loading…') loadMore.textContent = 'Load more members';
            }
        }
    };

    const updateAudienceFields = () => {
        const usesBranch = audience.value !== 'gym_wide';
        branchField.classList.toggle('hidden', !usesBranch);
        branch.disabled = !usesBranch;
        branch.required = audience.value === 'branch_specific';
        membersField.classList.toggle('hidden', audience.value !== 'selected_members');
        hiddenInputs.querySelectorAll('input').forEach((input) => { input.disabled = audience.value !== 'selected_members'; });
        updateSummary();
    };

    form.querySelector('[data-open-member-picker]').addEventListener('click', () => {
        dialogScope.textContent = branch.value
            ? `Showing eligible members in ${branch.selectedOptions[0]?.textContent || 'this branch'}.`
            : 'Showing eligible members across accessible branches.';
        dialog.showModal();
        search.focus();
        void searchMembers();
    });
    form.querySelectorAll('[data-close-member-picker]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    search.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => { void searchMembers(); }, 250);
    });
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            window.clearTimeout(searchTimer);
            void searchMembers();
        }
    });
    loadMore.addEventListener('click', () => { if (nextPage) void searchMembers(nextPage); });
    branch.addEventListener('change', () => {
        window.clearTimeout(searchTimer);
        request?.abort();
        request = null;
        loading = false;
        selected.clear();
        renderSelection();
        search.value = '';
        if (dialog.open) void searchMembers();
        else showResultMessage('Open the picker to browse eligible members.');
        loadMore.classList.add('hidden');
    });
    audience.addEventListener('change', updateAudienceFields);
    renderSelection();
    updateAudienceFields();
};
