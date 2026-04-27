import './bootstrap';

const dropdownRoots = document.querySelectorAll('[data-dropdown-root]');

const closeAllDropdowns = () => {
    dropdownRoots.forEach((root) => {
        const button = root.querySelector('[data-dropdown-toggle]');
        const menu = root.querySelector('[data-dropdown-menu]');

        if (!button || !menu) {
            return;
        }

        button.setAttribute('aria-expanded', 'false');
        menu.classList.remove('show');
    });
};

dropdownRoots.forEach((root) => {
    const button = root.querySelector('[data-dropdown-toggle]');
    const menu = root.querySelector('[data-dropdown-menu]');

    if (!button || !menu) {
        return;
    }

    button.addEventListener('click', (event) => {
        event.preventDefault();

        const isOpen = button.getAttribute('aria-expanded') === 'true';
        closeAllDropdowns();

        if (!isOpen) {
            button.setAttribute('aria-expanded', 'true');
            menu.classList.add('show');
        }
    });
});

document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const clickedDropdown = target.closest('[data-dropdown-root]');
    if (!clickedDropdown) {
        closeAllDropdowns();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAllDropdowns();
    }
});

const sidebar = document.getElementById('adminSidebar');
const sidebarToggleButton = document.querySelector('[data-sidebar-toggle]');

if (sidebar && sidebarToggleButton) {
    sidebarToggleButton.addEventListener('click', () => {
        const isOpen = sidebar.classList.toggle('is-open');
        sidebarToggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (window.innerWidth >= 992) {
            return;
        }

        const clickInsideSidebar = target.closest('#adminSidebar');
        const clickOnToggle = target.closest('[data-sidebar-toggle]');
        if (!clickInsideSidebar && !clickOnToggle) {
            sidebar.classList.remove('is-open');
            sidebarToggleButton.setAttribute('aria-expanded', 'false');
        }
    });
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const message = form.getAttribute('data-confirm');
    if (!message) {
        return;
    }

    if (!window.confirm(message)) {
        event.preventDefault();
    }
});

const parseDebounce = (value, fallback) => {
    const parsed = Number.parseInt(value ?? '', 10);

    if (Number.isNaN(parsed) || parsed < 0) {
        return fallback;
    }

    return parsed;
};

const toQueryParams = (form) => {
    const formData = new FormData(form);
    const params = new URLSearchParams();

    formData.forEach((rawValue, key) => {
        if (typeof rawValue !== 'string') {
            return;
        }

        const value = rawValue.trim();
        if (value === '') {
            return;
        }

        params.append(key, value);
    });

    return params;
};

const updateLiveTableExportLinks = (form, params) => {
    const selector = form.dataset.liveTableExportSelector;
    if (!selector) {
        return;
    }

    const links = document.querySelectorAll(selector);
    const exportParams = new URLSearchParams(params);
    exportParams.delete('page');

    links.forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        if (!link.dataset.liveTableBaseHref) {
            const baseUrl = new URL(link.href, window.location.origin);
            baseUrl.search = '';
            baseUrl.hash = '';
            link.dataset.liveTableBaseHref = baseUrl.pathname;
        }

        const baseHref = link.dataset.liveTableBaseHref ?? link.getAttribute('href') ?? '';
        const query = exportParams.toString();
        link.setAttribute('href', query === '' ? baseHref : `${baseHref}?${query}`);
    });
};

const updateLiveTableBrowserUrl = (form, params) => {
    const historyEndpoint = form.dataset.liveTableHistoryEndpoint || form.getAttribute('action') || window.location.pathname;
    const historyUrl = new URL(historyEndpoint, window.location.origin);
    const historyParams = new URLSearchParams(params);

    if (historyParams.get('page') === '1') {
        historyParams.delete('page');
    }

    const query = historyParams.toString();
    const nextUrl = query === '' ? historyUrl.pathname : `${historyUrl.pathname}?${query}`;
    window.history.replaceState({}, '', nextUrl);
};

const initLiveTable = (form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const targetSelector = form.dataset.liveTableTarget;
    if (!targetSelector) {
        return;
    }

    const target = document.querySelector(targetSelector);
    if (!(target instanceof HTMLElement)) {
        return;
    }

    const endpoint = form.dataset.liveTableEndpoint || form.getAttribute('action') || window.location.pathname;
    const responseSelector = form.dataset.liveTableResponseSelector || targetSelector;
    const debounceMs = parseDebounce(form.dataset.liveTableDebounce, 350);

    const debounceFieldSelector = form.dataset.liveTableDebounceFields || 'input[name="q"], input[type="search"], input[data-live-table-search]';
    const immediateFieldSelector = form.dataset.liveTableImmediateFields || 'select, input[type="date"], input[data-live-table-immediate]';

    const debounceFields = Array.from(form.querySelectorAll(debounceFieldSelector))
        .filter((field) => field instanceof HTMLElement);
    const immediateFields = Array.from(form.querySelectorAll(immediateFieldSelector))
        .filter((field) => field instanceof HTMLElement);

    let debounceTimer = null;
    let requestController = null;

    const load = (params) => {
        if (requestController) {
            requestController.abort();
        }

        requestController = new AbortController();
        const requestUrl = new URL(endpoint, window.location.origin);
        requestUrl.search = params.toString();

        fetch(requestUrl.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: requestController.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Falha ao atualizar listagem.');
                }

                return response.text();
            })
            .then((html) => {
                const parser = new DOMParser();
                const parsed = parser.parseFromString(html, 'text/html');
                const replacement = parsed.querySelector(responseSelector);

                target.innerHTML = replacement ? replacement.innerHTML : html;
                updateLiveTableBrowserUrl(form, params);
                updateLiveTableExportLinks(form, params);
            })
            .catch((error) => {
                if (error.name === 'AbortError') {
                    return;
                }

                console.error(error);
            });
    };

    const loadFromForm = () => {
        const params = toQueryParams(form);
        load(params);
    };

    const debouncedLoad = () => {
        if (debounceTimer) {
            window.clearTimeout(debounceTimer);
        }

        debounceTimer = window.setTimeout(() => {
            loadFromForm();
        }, debounceMs);
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        loadFromForm();
    });

    debounceFields.forEach((field) => {
        field.addEventListener('input', debouncedLoad);
    });

    immediateFields.forEach((field) => {
        field.addEventListener('change', () => {
            loadFromForm();
        });
    });

    target.addEventListener('click', (event) => {
        const targetElement = event.target;
        if (!(targetElement instanceof HTMLElement)) {
            return;
        }

        const paginationLink = targetElement.closest('.pagination a');
        if (!(paginationLink instanceof HTMLAnchorElement)) {
            return;
        }

        event.preventDefault();

        const url = new URL(paginationLink.href, window.location.origin);
        const params = new URLSearchParams(url.search);
        load(params);
    });

    updateLiveTableExportLinks(form, toQueryParams(form));
};

document.querySelectorAll('[data-live-table-form]').forEach((form) => {
    initLiveTable(form);
});
