// Sidebar visits keep the panel shell mounted while Laravel continues to render
// each destination normally. Other links and forms retain browser navigation.
export const initializePanelNavigation = (initializePage) => {
    const panelKind = document.body.dataset.panelKind;
    const routePrefix = panelKind === 'admin' ? '/admin/' : panelKind === 'gym' ? '/gym/' : null;
    if (!routePrefix) return;

    let currentRequest;
    let visit = 0;
    let scrollFrame;
    history.scrollRestoration = 'manual';

    const pageState = (scrollY = window.scrollY) => ({
        ...history.state,
        panelNavigation: true,
        panelScrollY: scrollY,
    });

    history.replaceState(pageState(), '', window.location.href);

    const saveScroll = () => history.replaceState(pageState(), '', window.location.href);

    const sidebarLinks = (sidebar) => [...sidebar.querySelectorAll('nav a[href]')];

    const matchingSidebar = (nextSidebar) => {
        const current = sidebarLinks(document.getElementById('app-sidebar'));
        const next = sidebarLinks(nextSidebar);
        return current.length === next.length && current.every((link, index) => link.href === next[index].href);
    };

    const syncSidebar = (nextSidebar) => {
        const current = sidebarLinks(document.getElementById('app-sidebar'));
        const next = sidebarLinks(nextSidebar);
        current.forEach((link, index) => {
            link.className = next[index].className;
            link.firstElementChild.className = next[index].firstElementChild.className;
            if (next[index].hasAttribute('aria-current')) {
                link.setAttribute('aria-current', next[index].getAttribute('aria-current'));
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    const runPageScripts = (scripts) => {
        scripts.forEach((original) => {
            const script = document.createElement('script');
            if (original.nonce) script.nonce = original.nonce;
            script.textContent = original.textContent;
            document.body.appendChild(script);
            script.remove();
        });
    };

    const navigate = async (url, { pop = false, refresh = false, scrollY = 0 } = {}) => {
        currentRequest?.abort();
        const controller = new AbortController();
        currentRequest = controller;
        const thisVisit = ++visit;
        const main = document.querySelector('#app-main main');
        main?.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: refresh ? 'no-store' : 'default',
                headers: { Accept: 'text/html' },
                signal: controller.signal,
            });
            if (!response.ok || response.redirected) throw new Error('Panel navigation failed');
            const html = await response.text();
            if (thisVisit !== visit) return;

            const next = new DOMParser().parseFromString(html, 'text/html');
            const nextSidebar = next.getElementById('app-sidebar');
            const nextHeader = next.querySelector('#app-main > header');
            const nextMain = next.querySelector('#app-main > main');
            const scripts = [...next.body.querySelectorAll('script')];
            const currentAppScript = document.head.querySelector('script[type="module"][src*="/build/assets/app-"]')?.src;
            const nextAppScript = next.head.querySelector('script[type="module"][src*="/build/assets/app-"]')?.src;
            const safe = next.body.dataset.panelKind === panelKind
                && nextSidebar && nextHeader && nextMain && matchingSidebar(nextSidebar)
                && !scripts.some((script) => script.src || (script.type && script.type !== 'text/javascript'))
                && currentAppScript === nextAppScript;
            if (!safe) throw new Error('Destination requires a full page load');

            window.panelPageController?.abort();
            window.panelPageController = new AbortController();
            document.querySelector('#app-main > header').replaceWith(nextHeader);
            main.replaceWith(nextMain);
            syncSidebar(nextSidebar);
            document.title = next.title;
            const token = next.querySelector('meta[name="csrf-token"]')?.content;
            if (token) {
                document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
                const modalToken = document.querySelector('#confirm-modal-form input[name="_token"]');
                if (modalToken) modalToken.value = token;
            }

            if (!pop && !refresh) history.pushState(pageState(0), '', response.url);
            if (window.innerWidth < 1280 && document.body.classList.contains('panel-sidebar-mobile-open')) {
                document.getElementById('sidebar-close-mobile')?.click();
            }
            initializePage();
            runPageScripts(scripts);
            document.querySelector('#app-main > header h1')?.setAttribute('tabindex', '-1');
            document.querySelector('#app-main > header h1')?.focus({ preventScroll: true });
            window.scrollTo(0, scrollY);
            document.dispatchEvent(new CustomEvent('panel:navigated', { detail: { url: response.url } }));
        } catch (error) {
            if (controller.signal.aborted || thisVisit !== visit) return;
            if (pop) window.location.reload();
            else window.location.assign(url);
        } finally {
            main?.removeAttribute('aria-busy');
            if (currentRequest === controller) currentRequest = undefined;
        }
    };

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const link = event.target.closest('#app-sidebar nav a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target && link.target !== '_self') return;
        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin || !destination.pathname.startsWith(routePrefix)) return;
        event.preventDefault();
        saveScroll();
        if (destination.href === window.location.href) {
            void navigate(destination.href, { refresh: true });
            return;
        }
        void navigate(destination.href);
    });

    window.addEventListener('popstate', (event) => {
        if (!event.state?.panelNavigation || !window.location.pathname.startsWith(routePrefix)) return;
        void navigate(window.location.href, { pop: true, scrollY: event.state.panelScrollY || 0 });
    });

    window.addEventListener('scroll', () => {
        if (scrollFrame) return;
        scrollFrame = requestAnimationFrame(() => {
            saveScroll();
            scrollFrame = undefined;
        });
    }, { passive: true });

    window.addEventListener('beforeunload', saveScroll);
};
