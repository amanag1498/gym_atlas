import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

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
    document.getElementById('sidebar-toggle-mobile')?.setAttribute('aria-expanded', 'true');
};

const syncMobileSidebarState = () => {
    document.getElementById('sidebar-toggle-mobile')?.setAttribute(
        'aria-expanded',
        document.body.classList.contains('panel-sidebar-mobile-open') ? 'true' : 'false',
    );
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
        syncMobileSidebarState();
    });

    document.getElementById('mobile-sidebar-backdrop')?.addEventListener('click', () => {
        closeMobileSidebar();
        syncMobileSidebarState();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileSidebar();
            syncMobileSidebarState();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth < 1280) {
            closeMobileSidebar();
            syncMobileSidebarState();
            return;
        }

        const persistedCollapsed = localStorage.getItem(sidebarStorageKey) === 'true';
        applySidebarState(persistedCollapsed);
    });

    const publicNavToggle = document.querySelector('[data-public-nav-toggle]');
    const publicNavMenu = document.querySelector('[data-public-nav-menu]');

    publicNavToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        const isOpen = publicNavMenu?.classList.toggle('show') ?? false;
        publicNavToggle.setAttribute('aria-expanded', String(isOpen));
    });

    publicNavMenu?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                publicNavMenu.classList.remove('show');
                publicNavToggle?.setAttribute('aria-expanded', 'false');
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            publicNavMenu?.classList.remove('show');
            publicNavToggle?.setAttribute('aria-expanded', 'false');
        }
    });

    syncMobileSidebarState();
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

let geocodingQueue = Promise.resolve();
let lastGeocodingRequestAt = 0;

const requestOpenStreetMap = (url) => {
    const request = async () => {
        const elapsed = Date.now() - lastGeocodingRequestAt;

        if (elapsed < 1100) {
            await new Promise((resolve) => window.setTimeout(resolve, 1100 - elapsed));
        }

        lastGeocodingRequestAt = Date.now();
        const response = await fetch(url, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            throw new Error('The map search service is temporarily unavailable.');
        }

        return response.json();
    };

    const queued = geocodingQueue.then(request, request);
    geocodingQueue = queued.catch(() => undefined);

    return queued;
};

const initializeLocationPickers = () => {
    document.querySelectorAll('[data-location-picker]').forEach((picker) => {
        const mapElement = picker.querySelector('[data-location-map]');
        const addressInput = picker.querySelector('[data-location-address]');
        const latitudeInput = picker.querySelector('[data-location-latitude]');
        const longitudeInput = picker.querySelector('[data-location-longitude]');
        const cityInput = picker.querySelector('[data-location-city]');
        const stateInput = picker.querySelector('[data-location-state]');
        const pincodeInput = picker.querySelector('[data-location-pincode]');
        const countryInput = picker.querySelector('[data-location-country]');
        const locationNameInput = picker.dataset.locationNameTarget
            ? document.getElementById(picker.dataset.locationNameTarget)
            : null;
        const results = picker.querySelector('[data-location-results]');
        const status = picker.querySelector('[data-location-status]');
        const searchButton = picker.querySelector('[data-location-search]');
        const currentButton = picker.querySelector('[data-location-current]');
        const presetButton = picker.querySelector('[data-location-use-preset]');

        if (!mapElement || !addressInput || !latitudeInput || !longitudeInput) {
            return;
        }

        const initialLatitude = Number.parseFloat(picker.dataset.initialLatitude);
        const initialLongitude = Number.parseFloat(picker.dataset.initialLongitude);
        const hasInitialCoordinates = Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude);
        const map = L.map(mapElement, { scrollWheelZoom: false }).setView(
            hasInitialCoordinates ? [initialLatitude, initialLongitude] : [22.5937, 78.9629],
            hasInitialCoordinates ? 16 : 5,
        );
        let marker = null;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const setStatus = (message, isError = false) => {
            status.textContent = message;
            status.classList.toggle('text-rose-600', isError);
            status.classList.toggle('dark:text-rose-400', isError);
        };

        const setCoordinates = (latitude, longitude, moveMap = true) => {
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            latitudeInput.value = latitude.toFixed(7);
            longitudeInput.value = longitude.toFixed(7);

            if (!marker) {
                marker = L.circleMarker([latitude, longitude], {
                    radius: 9,
                    color: '#ffffff',
                    weight: 3,
                    fillColor: '#465fff',
                    fillOpacity: 1,
                }).addTo(map);
            } else {
                marker.setLatLng([latitude, longitude]);
            }

            if (moveMap) {
                map.setView([latitude, longitude], Math.max(map.getZoom(), 16));
            }
        };

        const applyAddress = (payload, fallbackAddress = '') => {
            const details = payload?.address || {};
            const addressMax = Number.parseInt(picker.dataset.addressMax || '255', 10);
            addressInput.value = (payload?.display_name || fallbackAddress || addressInput.value).slice(0, addressMax);

            if (cityInput) {
                cityInput.value = details.city || details.town || details.village || details.municipality || details.county || cityInput.value;
            }
            if (stateInput) {
                stateInput.value = details.state || stateInput.value;
            }
            if (pincodeInput) {
                pincodeInput.value = details.postcode || pincodeInput.value;
            }
            if (countryInput) {
                countryInput.value = details.country || countryInput.value;
            }
        };

        const chooseLocation = (payload) => {
            const latitude = Number.parseFloat(payload.lat);
            const longitude = Number.parseFloat(payload.lon);
            setCoordinates(latitude, longitude);
            applyAddress(payload);
            results.replaceChildren();
            results.classList.add('hidden');
            setStatus('Address and map pin selected.');
        };

        const reverseGeocode = async (latitude, longitude) => {
            setStatus('Pin selected. Finding the address…');

            try {
                const payload = await requestOpenStreetMap(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(latitude)}&lon=${encodeURIComponent(longitude)}&addressdetails=1&accept-language=en`);
                applyAddress(payload);
                setStatus('Address and map pin selected.');
            } catch (error) {
                setStatus('The pin is saved, but the address could not be filled automatically. You can type it manually.', true);
            }
        };

        const search = async () => {
            const query = addressInput.value.trim();

            if (query.length < 3) {
                setStatus('Enter at least 3 characters to search.', true);
                addressInput.focus();
                return;
            }

            searchButton.disabled = true;
            setStatus('Searching the map…');

            try {
                const payload = await requestOpenStreetMap(`https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(query)}&limit=5&addressdetails=1&accept-language=en`);
                results.replaceChildren();

                if (!Array.isArray(payload) || payload.length === 0) {
                    results.classList.add('hidden');
                    setStatus('No matching place was found. Try adding the city or pincode.', true);
                    return;
                }

                payload.forEach((place) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'block w-full border-b border-slate-100 px-4 py-3 text-left text-sm text-slate-700 transition last:border-0 hover:bg-brand-50 hover:text-brand-700 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-brand-500/10';
                    button.textContent = place.display_name;
                    button.addEventListener('click', () => chooseLocation(place));
                    results.appendChild(button);
                });

                results.classList.remove('hidden');
                setStatus('Choose the correct result below.');
            } catch (error) {
                results.classList.add('hidden');
                setStatus(error.message || 'Map search failed. You can still type the address manually.', true);
            } finally {
                searchButton.disabled = false;
            }
        };

        map.on('click', ({ latlng }) => {
            setCoordinates(latlng.lat, latlng.lng, false);
            reverseGeocode(latlng.lat, latlng.lng);
        });

        searchButton?.addEventListener('click', search);
        addressInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                search();
            }
        });

        currentButton?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                setStatus('Location access is not available in this browser.', true);
                return;
            }

            currentButton.disabled = true;
            setStatus('Getting your current location…');
            navigator.geolocation.getCurrentPosition(
                ({ coords }) => {
                    setCoordinates(coords.latitude, coords.longitude);
                    reverseGeocode(coords.latitude, coords.longitude).finally(() => {
                        currentButton.disabled = false;
                    });
                },
                () => {
                    currentButton.disabled = false;
                    setStatus('Location access was not allowed. Search the address or tap the map instead.', true);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
            );
        });

        presetButton?.addEventListener('click', () => {
            try {
                const preset = JSON.parse(picker.dataset.locationPreset || '{}');
                const addressMax = Number.parseInt(picker.dataset.addressMax || '255', 10);
                addressInput.value = (preset.address || '').slice(0, addressMax);
                if (cityInput) cityInput.value = preset.city || '';
                if (stateInput) stateInput.value = preset.state || '';
                if (pincodeInput) pincodeInput.value = preset.pincode || '';
                if (countryInput) countryInput.value = preset.country || '';
                if (locationNameInput) locationNameInput.value = preset.location_name || locationNameInput.value;

                const latitude = Number.parseFloat(preset.latitude);
                const longitude = Number.parseFloat(preset.longitude);
                if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
                    setCoordinates(latitude, longitude);
                    setStatus('Gym address and map pin applied.');
                } else {
                    latitudeInput.value = '';
                    longitudeInput.value = '';
                    setStatus('Gym address applied. Search it to add a precise map pin.');
                }
            } catch (error) {
                setStatus('The saved gym address could not be applied.', true);
            }
        });

        [latitudeInput, longitudeInput].forEach((input) => {
            input.addEventListener('change', () => {
                const latitude = Number.parseFloat(latitudeInput.value);
                const longitude = Number.parseFloat(longitudeInput.value);
                if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
                    setCoordinates(latitude, longitude);
                    setStatus('Manual coordinates applied.');
                }
            });
        });

        if (hasInitialCoordinates) {
            setCoordinates(initialLatitude, initialLongitude, false);
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initializePanelChrome();
    initializeConfirmationModal();
    initializePreloader();
    initializeLocationPickers();
});
