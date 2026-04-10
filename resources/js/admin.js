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
