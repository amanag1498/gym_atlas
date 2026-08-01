const publicHeader = document.querySelector('[data-public-header]');
const publicNavToggle = document.querySelector('[data-public-nav-toggle]');
const publicNavMenu = document.querySelector('[data-public-nav-menu]');
const publicNavClose = document.querySelector('[data-public-nav-close]');
const publicNavBackdrop = document.querySelector('[data-public-nav-backdrop]');
const publicProductMenu = document.querySelector('[data-public-product-menu]');
const publicProductToggle = document.querySelector('[data-public-product-toggle]');
const publicProductPanel = document.querySelector('[data-public-product-panel]');
const mobileNavigationQuery = window.matchMedia('(max-width: 991.98px)');

const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

let lastFocusedElement = null;

const setProductMenuState = (isOpen) => {
    if (!publicProductToggle || !publicProductPanel) {
        return;
    }

    publicProductPanel.classList.toggle('is-open', isOpen);
    publicProductToggle.setAttribute('aria-expanded', String(isOpen));
    publicProductPanel.setAttribute('aria-hidden', String(!isOpen));
    publicProductPanel.inert = !isOpen;
};

const setPublicNavigationState = (isOpen, returnFocus = false) => {
    if (!publicNavToggle || !publicNavMenu) {
        return;
    }

    const shouldOpen = isOpen && mobileNavigationQuery.matches;

    publicNavMenu.classList.toggle('show', shouldOpen);
    publicNavToggle.setAttribute('aria-expanded', String(shouldOpen));
    document.body.classList.toggle('public-navigation-open', shouldOpen);

    if (mobileNavigationQuery.matches) {
        publicNavMenu.setAttribute('role', 'dialog');
        publicNavMenu.setAttribute('aria-modal', 'true');
        publicNavMenu.setAttribute('aria-label', 'Site navigation');
        publicNavMenu.inert = !shouldOpen;
    } else {
        publicNavMenu.removeAttribute('role');
        publicNavMenu.removeAttribute('aria-modal');
        publicNavMenu.removeAttribute('aria-label');
        publicNavMenu.inert = false;
    }

    if (shouldOpen) {
        lastFocusedElement = document.activeElement;
        window.requestAnimationFrame(() => publicNavClose?.focus());
    } else {
        setProductMenuState(false);

        if (returnFocus && lastFocusedElement instanceof HTMLElement) {
            lastFocusedElement.focus();
        }
    }
};

const closePublicNavigation = (returnFocus = false) => setPublicNavigationState(false, returnFocus);

publicNavToggle?.addEventListener('click', () => {
    setPublicNavigationState(!publicNavMenu?.classList.contains('show'));
});

publicNavClose?.addEventListener('click', () => closePublicNavigation(true));
publicNavBackdrop?.addEventListener('click', () => closePublicNavigation(true));

publicProductToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    setProductMenuState(publicProductToggle.getAttribute('aria-expanded') !== 'true');
});

publicNavMenu?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
        if (mobileNavigationQuery.matches) {
            closePublicNavigation();
        }
    });
});

document.addEventListener('click', (event) => {
    if (publicProductMenu && !publicProductMenu.contains(event.target)) {
        setProductMenuState(false);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        if (publicNavMenu?.classList.contains('show')) {
            closePublicNavigation(true);
        } else if (publicProductToggle?.getAttribute('aria-expanded') === 'true') {
            setProductMenuState(false);
            publicProductToggle.focus();
        }
        return;
    }

    if (event.key !== 'Tab' || !mobileNavigationQuery.matches || !publicNavMenu?.classList.contains('show')) {
        return;
    }

    const focusableElements = [...publicNavMenu.querySelectorAll(focusableSelector)]
        .filter((element) => element.offsetParent !== null);
    const firstElement = focusableElements[0];
    const lastElement = focusableElements.at(-1);

    if (!firstElement || !lastElement) {
        return;
    }

    if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault();
        lastElement.focus();
    } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
    }
});

const handleNavigationBreakpointChange = () => {
    closePublicNavigation();
    setPublicNavigationState(false);
};

mobileNavigationQuery.addEventListener?.('change', handleNavigationBreakpointChange);

const updateHeaderState = () => {
    publicHeader?.classList.toggle('is-scrolled', window.scrollY > 12);
};

updateHeaderState();
setPublicNavigationState(false);
window.addEventListener('scroll', updateHeaderState, { passive: true });

const gymFilterOpen = document.querySelector('[data-gym-filter-open]');
const gymFilterPanel = document.querySelector('[data-gym-filter-panel]');
const gymFilterClose = document.querySelector('[data-gym-filter-close]');
const gymFilterBackdrop = document.querySelector('[data-gym-filter-backdrop]');
const mobileGymFilterQuery = window.matchMedia('(max-width: 767.98px)');

let lastFocusedGymFilterElement = null;

const setGymFilterState = (isOpen, returnFocus = false) => {
    if (!gymFilterOpen || !gymFilterPanel) {
        return;
    }

    const shouldOpen = isOpen && mobileGymFilterQuery.matches;

    gymFilterPanel.classList.toggle('is-open', shouldOpen);
    gymFilterOpen.setAttribute('aria-expanded', String(shouldOpen));
    document.body.classList.toggle('gym-filter-drawer-open', shouldOpen);

    if (mobileGymFilterQuery.matches) {
        gymFilterPanel.setAttribute('role', 'dialog');
        gymFilterPanel.setAttribute('aria-modal', 'true');
        gymFilterPanel.setAttribute('aria-labelledby', 'gym-filter-drawer-title');
        gymFilterPanel.setAttribute('aria-hidden', String(!shouldOpen));
        gymFilterPanel.inert = !shouldOpen;
    } else {
        gymFilterPanel.removeAttribute('role');
        gymFilterPanel.removeAttribute('aria-modal');
        gymFilterPanel.removeAttribute('aria-labelledby');
        gymFilterPanel.removeAttribute('aria-hidden');
        gymFilterPanel.inert = false;
    }

    if (shouldOpen) {
        closePublicNavigation();
        lastFocusedGymFilterElement = document.activeElement;
        window.requestAnimationFrame(() => gymFilterClose?.focus());
    } else if (returnFocus && lastFocusedGymFilterElement instanceof HTMLElement) {
        lastFocusedGymFilterElement.focus();
    }
};

gymFilterOpen?.addEventListener('click', () => setGymFilterState(true));
gymFilterClose?.addEventListener('click', () => setGymFilterState(false, true));
gymFilterBackdrop?.addEventListener('click', () => setGymFilterState(false, true));

document.addEventListener('keydown', (event) => {
    if (!mobileGymFilterQuery.matches || !gymFilterPanel?.classList.contains('is-open')) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        setGymFilterState(false, true);
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusableElements = [...gymFilterPanel.querySelectorAll(focusableSelector)]
        .filter((element) => element.offsetParent !== null);
    const firstElement = focusableElements[0];
    const lastElement = focusableElements.at(-1);

    if (!firstElement || !lastElement) {
        return;
    }

    if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault();
        lastElement.focus();
    } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
    }
});

mobileGymFilterQuery.addEventListener?.('change', () => setGymFilterState(false));
setGymFilterState(false);

const faqSearch = document.querySelector('[data-faq-search]');
const faqItems = [...document.querySelectorAll('[data-faq-item]')];
const faqFilters = [...document.querySelectorAll('[data-faq-filter]')];
const faqCount = document.querySelector('[data-faq-count]');
const faqEmpty = document.querySelector('[data-faq-empty]');

let activeFaqFilter = 'all';

const updateFaqResults = () => {
    if (!faqItems.length) {
        return;
    }

    const query = faqSearch?.value.trim().toLocaleLowerCase() ?? '';
    let visibleCount = 0;

    faqItems.forEach((item) => {
        const matchesFilter = activeFaqFilter === 'all' || item.dataset.faqCategory === activeFaqFilter;
        const matchesSearch = !query || item.dataset.faqText?.includes(query);
        const isVisible = matchesFilter && matchesSearch;

        item.hidden = !isVisible;
        if (!isVisible) {
            item.removeAttribute('open');
        } else {
            visibleCount += 1;
        }
    });

    if (faqCount) {
        faqCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'answer' : 'answers'}`;
    }

    if (faqEmpty) {
        faqEmpty.hidden = visibleCount !== 0;
    }
};

faqSearch?.addEventListener('input', updateFaqResults);
faqFilters.forEach((filter) => {
    filter.addEventListener('click', () => {
        activeFaqFilter = filter.dataset.faqFilter ?? 'all';
        faqFilters.forEach((candidate) => {
            candidate.setAttribute('aria-pressed', String(candidate === filter));
        });
        updateFaqResults();
    });
});
