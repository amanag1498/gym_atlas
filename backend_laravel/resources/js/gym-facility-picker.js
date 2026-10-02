export const initializeGymFacilityPicker = () => {
    const picker = document.querySelector('[data-facility-picker]');
    if (!picker) return;

    const search = picker.querySelector('[data-facility-search]');
    const rows = [...picker.querySelectorAll('[data-facility-option]')];
    const chips = picker.querySelector('[data-facility-chips]');
    const count = picker.querySelector('[data-facility-count]');
    const empty = picker.querySelector('[data-facility-empty]');
    const noResults = picker.querySelector('[data-facility-no-results]');
    const more = picker.querySelector('[data-facility-more]');
    let visibleLimit = 20;

    const selectedRows = () => rows.filter((row) => row.querySelector('input').checked);

    const renderSelection = () => {
        const selected = selectedRows();
        chips.replaceChildren();
        selected.forEach((row) => {
            const name = row.querySelector('span').textContent.trim();
            const chip = document.createElement('span');
            chip.className = 'inline-flex max-w-full items-center gap-1 rounded-full border border-brand-200 bg-brand-50 py-1 pl-3 pr-1 text-xs font-medium text-slate-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-slate-100';
            const label = document.createElement('span');
            label.className = 'break-words';
            label.textContent = name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-600 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-slate-300 dark:hover:bg-slate-700';
            remove.setAttribute('aria-label', `Remove ${name}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                row.querySelector('input').checked = false;
                renderSelection();
            });
            chip.append(label, remove);
            chips.append(chip);
        });
        count.textContent = `${selected.length} selected`;
        empty.classList.toggle('hidden', selected.length > 0);
    };

    const renderOptions = () => {
        const term = search.value.trim().toLocaleLowerCase();
        const matches = rows.filter((row) => row.dataset.facilityName.toLocaleLowerCase().includes(term));
        rows.forEach((row) => { row.style.display = 'none'; });
        matches.slice(0, visibleLimit).forEach((row) => { row.style.display = ''; });
        noResults.classList.toggle('hidden', matches.length > 0);
        more.classList.toggle('hidden', matches.length <= visibleLimit);
    };

    search.addEventListener('input', () => {
        visibleLimit = 20;
        renderOptions();
    });
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') event.preventDefault();
    });
    more.addEventListener('click', () => {
        visibleLimit += 20;
        renderOptions();
    });
    rows.forEach((row) => row.querySelector('input').addEventListener('change', renderSelection));
    renderSelection();
    renderOptions();
};
