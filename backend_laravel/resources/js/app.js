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

let googleMapsPromise;

const loadGoogleMaps = () => {
    if (window.google?.maps?.importLibrary) {
        return Promise.resolve(window.google.maps);
    }

    if (googleMapsPromise) {
        return googleMapsPromise;
    }

    const apiKey = document.querySelector('meta[name="google-maps-api-key"]')?.content?.trim();

    if (!apiKey) {
        return Promise.reject(new Error('Google Maps is not configured. Add GOOGLE_MAPS_BROWSER_KEY on the server.'));
    }

    googleMapsPromise = new Promise((resolve, reject) => {
        const callbackName = `gymAtlasGoogleMapsReady${Date.now()}`;
        const script = document.createElement('script');

        window[callbackName] = () => {
            delete window[callbackName];
            resolve(window.google.maps);
        };

        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&v=weekly&loading=async&callback=${callbackName}`;
        script.async = true;
        script.onerror = () => {
            delete window[callbackName];
            googleMapsPromise = undefined;
            reject(new Error('Google Maps could not be loaded. Check the browser key and its website/API restrictions.'));
        };
        document.head.appendChild(script);
    });

    return googleMapsPromise;
};

const locationErrorMessage = (error) => {
    if (error?.code === 1) {
        return 'Location permission is blocked. Allow Location for this site in browser settings, then try again.';
    }

    if (error?.code === 2) {
        return 'Your current location is unavailable. Search and select the address instead.';
    }

    if (error?.code === 3) {
        return 'Finding your location timed out. Try again or search the address instead.';
    }

    return 'Your location could not be read. Search and select the address instead.';
};

const initializeLocationPickers = async () => {
    const pickers = [...document.querySelectorAll('[data-location-picker]')];

    if (pickers.length === 0) {
        return;
    }

    let maps;
    let Map;
    let Geocoder;
    let AutocompleteSessionToken;
    let AutocompleteSuggestion;
    let AdvancedMarkerElement;

    try {
        maps = await loadGoogleMaps();
        const [mapsLibrary, placesLibrary, geocodingLibrary, markerLibrary] = await Promise.all([
            maps.importLibrary('maps'),
            maps.importLibrary('places'),
            maps.importLibrary('geocoding'),
            maps.importLibrary('marker'),
        ]);
        ({ Map } = mapsLibrary);
        ({ Geocoder } = geocodingLibrary);
        ({ AutocompleteSessionToken, AutocompleteSuggestion } = placesLibrary);
        ({ AdvancedMarkerElement } = markerLibrary);
    } catch (error) {
        pickers.forEach((picker) => {
            const status = picker.querySelector('[data-location-status]');
            const mapElement = picker.querySelector('[data-location-map]');

            if (status) {
                status.textContent = error.message;
                status.classList.add('text-rose-600', 'dark:text-rose-400');
            }
            if (mapElement) {
                mapElement.innerHTML = '<div class="flex h-full items-center justify-center px-6 text-center text-sm text-slate-500">Google Maps is unavailable. You can still enter the address manually.</div>';
            }
            picker.querySelector('[data-location-search]')?.setAttribute('disabled', 'disabled');
            picker.querySelector('[data-location-current]')?.setAttribute('disabled', 'disabled');
        });
        return;
    }

    const mapId = document.querySelector('meta[name="google-maps-id"]')?.content?.trim();
    const region = document.querySelector('meta[name="google-maps-region"]')?.content?.trim().toLowerCase() || 'in';

    pickers.forEach((picker) => {
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
        const mapOptions = {
            center: hasInitialCoordinates
                ? { lat: initialLatitude, lng: initialLongitude }
                : { lat: 22.5937, lng: 78.9629 },
            zoom: hasInitialCoordinates ? 16 : 5,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            gestureHandling: 'cooperative',
        };

        if (mapId) {
            mapOptions.mapId = mapId;
        }

        const map = new Map(mapElement, mapOptions);
        const geocoder = new Geocoder();
        let marker = null;
        let sessionToken = new AutocompleteSessionToken();
        let searchSequence = 0;
        let searchTimer;
        let selectedAddress = hasInitialCoordinates ? addressInput.value.trim() : '';
        let isApplyingLocation = false;

        const setStatus = (message, isError = false) => {
            status.textContent = message;
            status.classList.toggle('text-rose-600', isError);
            status.classList.toggle('dark:text-rose-400', isError);
        };

        const updateInput = (input, value, dispatchChange = true) => {
            if (!input) return;
            input.value = value ?? '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            if (dispatchChange) {
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        const clearCoordinates = () => {
            updateInput(latitudeInput, '', false);
            updateInput(longitudeInput, '', false);

            if (!marker) return;
            if (mapId) {
                marker.map = null;
            } else {
                marker.setMap(null);
            }
            marker = null;
        };

        const setCoordinates = (latitude, longitude, moveMap = true) => {
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            updateInput(latitudeInput, latitude.toFixed(7), false);
            updateInput(longitudeInput, longitude.toFixed(7), false);

            if (!marker) {
                marker = mapId
                    ? new AdvancedMarkerElement({
                        map,
                        position: { lat: latitude, lng: longitude },
                        title: 'Selected location',
                    })
                    : new maps.Marker({
                        map,
                        position: { lat: latitude, lng: longitude },
                        title: 'Selected location',
                    });
            } else if (mapId) {
                marker.position = { lat: latitude, lng: longitude };
            } else {
                marker.setPosition({ lat: latitude, lng: longitude });
            }

            if (moveMap) {
                map.panTo({ lat: latitude, lng: longitude });
                map.setZoom(Math.max(map.getZoom() || 0, 16));
            }
        };

        const componentValue = (components, types, short = false) => {
            const component = (components || []).find((item) => types.some((type) => item.types?.includes(type)));
            return short
                ? component?.shortText || component?.short_name || ''
                : component?.longText || component?.long_name || '';
        };

        const applyAddress = (payload, fallbackAddress = '') => {
            const components = payload?.addressComponents || payload?.address_components || [];
            const addressMax = Number.parseInt(picker.dataset.addressMax || '255', 10);
            const formattedAddress = (payload?.formattedAddress || payload?.formatted_address || fallbackAddress || addressInput.value).slice(0, addressMax);
            const city = componentValue(components, [
                'locality',
                'postal_town',
                'administrative_area_level_3',
                'sublocality_level_1',
                'administrative_area_level_2',
            ]);

            isApplyingLocation = true;
            updateInput(addressInput, formattedAddress);
            updateInput(cityInput, city);
            updateInput(stateInput, componentValue(components, ['administrative_area_level_1']));
            updateInput(pincodeInput, componentValue(components, ['postal_code']));
            updateInput(countryInput, componentValue(components, ['country']));

            if (locationNameInput && !locationNameInput.value.trim()) {
                updateInput(locationNameInput, payload?.displayName || payload?.name || '');
            }

            selectedAddress = formattedAddress.trim();
            isApplyingLocation = false;
        };

        const chooseLocation = async (prediction) => {
            setStatus('Loading the selected place…');
            const place = prediction.toPlace();
            await place.fetchFields({
                fields: ['displayName', 'formattedAddress', 'location', 'addressComponents'],
            });
            const latitude = place.location?.lat();
            const longitude = place.location?.lng();

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                throw new Error('Google Maps did not return coordinates for that place. Try another result.');
            }

            applyAddress(place);
            setCoordinates(latitude, longitude);
            results.replaceChildren();
            results.classList.add('hidden');
            addressInput.setAttribute('aria-expanded', 'false');
            sessionToken = new AutocompleteSessionToken();
            setStatus('Google Maps address and pin selected.');
        };

        const reverseGeocode = async (latitude, longitude) => {
            setStatus('Pin selected. Finding the address…');

            try {
                const response = await geocoder.geocode({ location: { lat: latitude, lng: longitude } });
                const place = response.results?.[0];

                if (!place) {
                    throw new Error('No address found.');
                }

                applyAddress(place);
                setCoordinates(latitude, longitude, false);
                setStatus('Google Maps address and pin selected.');
            } catch (error) {
                setStatus('The pin is saved, but the address could not be filled automatically. You can type it manually.', true);
            }
        };

        const search = async ({ selectFirst = false } = {}) => {
            const query = addressInput.value.trim();

            if (query.length < 3) {
                setStatus('Enter at least 3 characters to search.', true);
                addressInput.focus();
                return;
            }

            const sequence = ++searchSequence;
            if (searchButton) searchButton.disabled = true;
            setStatus('Searching Google Maps…');

            try {
                const response = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
                    input: query,
                    sessionToken,
                    region,
                    language: document.documentElement.lang || 'en',
                });

                if (sequence !== searchSequence) {
                    return;
                }

                const predictions = response.suggestions
                    .map((suggestion) => suggestion.placePrediction)
                    .filter(Boolean)
                    .slice(0, 5);
                results.replaceChildren();

                if (predictions.length === 0) {
                    results.classList.add('hidden');
                    addressInput.setAttribute('aria-expanded', 'false');
                    setStatus('No matching place was found. Try adding the city or pincode.', true);
                    return;
                }

                predictions.forEach((prediction) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.setAttribute('role', 'option');
                    button.className = 'atlas-location-suggestion block w-full border-b px-4 py-3 text-left text-sm transition last:border-0';
                    button.textContent = prediction.text.toString();
                    button.addEventListener('click', async () => {
                        button.disabled = true;
                        try {
                            await chooseLocation(prediction);
                        } catch (error) {
                            button.disabled = false;
                            setStatus(error.message || 'That place could not be selected.', true);
                        }
                    });
                    button.addEventListener('keydown', (event) => {
                        const options = [...results.querySelectorAll('[role="option"]')];
                        const index = options.indexOf(button);

                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            options[(index + 1) % options.length]?.focus();
                        } else if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            (index === 0 ? addressInput : options[index - 1])?.focus();
                        } else if (event.key === 'Escape') {
                            results.classList.add('hidden');
                            addressInput.setAttribute('aria-expanded', 'false');
                            addressInput.focus();
                        }
                    });
                    results.appendChild(button);
                });

                if (selectFirst) {
                    await chooseLocation(predictions[0]);
                    return;
                }

                results.classList.remove('hidden');
                addressInput.setAttribute('aria-expanded', 'true');
                setStatus('Choose the correct Google Maps result below.');
            } catch (error) {
                results.classList.add('hidden');
                addressInput.setAttribute('aria-expanded', 'false');
                setStatus(error.message || 'Google Maps search failed. You can still type the address manually.', true);
            } finally {
                if (searchButton) searchButton.disabled = false;
            }
        };

        map.addListener('click', (event) => {
            const latitude = event.latLng?.lat();
            const longitude = event.latLng?.lng();

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
            setCoordinates(latitude, longitude, false);
            void reverseGeocode(latitude, longitude);
        });

        searchButton?.addEventListener('click', () => void search());
        addressInput.addEventListener('input', () => {
            if (isApplyingLocation) {
                return;
            }

            if (addressInput.value.trim() !== selectedAddress) {
                selectedAddress = '';
                if (latitudeInput.value || longitudeInput.value) {
                    clearCoordinates();
                    setStatus('Address changed. Select a Google Maps result to save the matching coordinates.');
                }
            }

            window.clearTimeout(searchTimer);
            if (addressInput.value.trim().length < 3) {
                results.replaceChildren();
                results.classList.add('hidden');
                addressInput.setAttribute('aria-expanded', 'false');
                return;
            }
            searchTimer = window.setTimeout(() => void search(), 350);
        });
        addressInput.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && !results.classList.contains('hidden')) {
                event.preventDefault();
                results.querySelector('[role="option"]')?.focus();
                return;
            }

            if (event.key === 'Escape') {
                results.classList.add('hidden');
                addressInput.setAttribute('aria-expanded', 'false');
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                window.clearTimeout(searchTimer);
                void search({ selectFirst: true });
            }
        });

        currentButton?.addEventListener('click', () => {
            if (!window.isSecureContext) {
                setStatus('Current location requires HTTPS. Search and select the address instead.', true);
                return;
            }

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
                (error) => {
                    currentButton.disabled = false;
                    setStatus(locationErrorMessage(error), true);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
            );
        });

        presetButton?.addEventListener('click', () => {
            try {
                const preset = JSON.parse(picker.dataset.locationPreset || '{}');
                const addressMax = Number.parseInt(picker.dataset.addressMax || '255', 10);
                isApplyingLocation = true;
                updateInput(addressInput, (preset.address || '').slice(0, addressMax));
                updateInput(cityInput, preset.city || '');
                updateInput(stateInput, preset.state || '');
                updateInput(pincodeInput, preset.pincode || '');
                updateInput(countryInput, preset.country || '');
                if (locationNameInput) updateInput(locationNameInput, preset.location_name || locationNameInput.value);
                selectedAddress = addressInput.value.trim();
                isApplyingLocation = false;

                const latitude = Number.parseFloat(preset.latitude);
                const longitude = Number.parseFloat(preset.longitude);
                if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
                    setCoordinates(latitude, longitude);
                    setStatus('Gym address and map pin applied.');
                } else {
                    clearCoordinates();
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
    void initializeLocationPickers();
});
