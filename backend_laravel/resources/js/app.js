const themeStorageKey = 'gym-ecosystem-panel-theme';
const sidebarStorageKey = 'gym-ecosystem-panel-sidebar-collapsed';

const applyTheme = (theme) => {
    const root = document.documentElement;
    const body = document.body;

    if (theme === 'dark') {
        root.classList.add('dark');
        body?.classList.add('dark');
    } else {
        root.classList.remove('dark');
        body?.classList.remove('dark');
    }
};

const applySidebarState = (collapsed) => {
    document.body.classList.toggle('panel-sidebar-collapsed', collapsed);
};

const closeMobileSidebar = () => {
    document.body.classList.remove('panel-sidebar-mobile-open');
};

const openMobileSidebar = () => {
    document.body.classList.add('panel-sidebar-mobile-open');
};

const initializePanelChrome = () => {
    const savedTheme = localStorage.getItem(themeStorageKey);
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(savedTheme || (systemPrefersDark ? 'dark' : 'light'));

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
        if (localStorage.getItem(themeStorageKey)) {
            return;
        }

        applyTheme(event.matches ? 'dark' : 'light');
    });

    const savedSidebarState = localStorage.getItem(sidebarStorageKey) === 'true';
    applySidebarState(savedSidebarState && window.innerWidth >= 1280);

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        localStorage.setItem(themeStorageKey, nextTheme);
        applyTheme(nextTheme);
    });

    document.getElementById('sidebar-toggle-desktop')?.addEventListener('click', () => {
        const collapsed = !document.body.classList.contains('panel-sidebar-collapsed');
        localStorage.setItem(sidebarStorageKey, String(collapsed));
        applySidebarState(collapsed);
    });

    document.getElementById('sidebar-toggle-mobile')?.addEventListener('click', () => {
        openMobileSidebar();
    });

    document.getElementById('sidebar-close-mobile')?.addEventListener('click', () => {
        closeMobileSidebar();
    });

    document.getElementById('mobile-sidebar-backdrop')?.addEventListener('click', () => {
        closeMobileSidebar();
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth < 1280) {
            closeMobileSidebar();
            return;
        }

        const persistedCollapsed = localStorage.getItem(sidebarStorageKey) === 'true';
        applySidebarState(persistedCollapsed);
    });
};

const initializeConfirmationModal = () => {
    const modal = document.getElementById('confirm-modal');
    const modalForm = document.getElementById('confirm-modal-form');
    const modalPayload = document.getElementById('confirm-modal-hidden-payload');
    const modalTitle = document.getElementById('confirm-modal-title');
    const modalMessage = document.getElementById('confirm-modal-message');
    const modalButton = document.getElementById('confirm-modal-submit');

    document.querySelectorAll('[data-confirm-action]').forEach((element) => {
        element.addEventListener('click', (event) => {
            event.preventDefault();

            if (!modal || !modalForm) {
                return;
            }

            modalTitle.textContent = element.dataset.confirmTitle || 'Confirm action';
            modalMessage.textContent = element.dataset.confirmMessage || 'Please confirm this action.';
            modalButton.textContent = element.dataset.confirmButton || 'Confirm';
            modalForm.setAttribute('action', element.getAttribute('href') || element.dataset.action || '#');

            if (modalPayload) {
                modalPayload.innerHTML = '';
            }

            modal.showModal();
        });
    });

    document.querySelectorAll('[data-confirm-submit]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!modal || !modalForm) {
                return;
            }

            event.preventDefault();
            modalTitle.textContent = form.dataset.confirmTitle || 'Confirm action';
            modalMessage.textContent = form.dataset.confirmMessage || 'Please confirm this action.';
            modalButton.textContent = form.dataset.confirmButton || 'Confirm';
            modalForm.setAttribute('action', form.getAttribute('action') || '#');

            if (modalPayload) {
                modalPayload.innerHTML = form.querySelector('[data-confirm-payload]')?.innerHTML || '';
            }

            modal.showModal();
        });
    });

    document.querySelectorAll('[data-close-confirm-modal]').forEach((button) => {
        button.addEventListener('click', () => modal?.close());
    });
};

const initializePreloader = () => {
    const preloader = document.getElementById('panel-preloader');

    if (!preloader) {
        return;
    }

    window.setTimeout(() => {
        preloader.classList.add('pointer-events-none', 'opacity-0');
        window.setTimeout(() => preloader.remove(), 300);
    }, 350);
};

document.addEventListener('DOMContentLoaded', () => {
    initializePanelChrome();
    initializeConfirmationModal();
    initializePreloader();
});
