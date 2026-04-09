<header class="admin-topbar navbar navbar-expand bg-body border-bottom px-3 py-2">
    <div class="d-flex align-items-center gap-2">
        <button
            type="button"
            class="btn btn-outline-secondary btn-sm d-lg-none"
            data-sidebar-toggle
            aria-controls="adminSidebar"
            aria-expanded="false"
            aria-label="Alternar menu lateral"
        >
            Menu
        </button>
        <span class="navbar-brand mb-0 h6 d-none d-md-inline">{{ config('app.name', 'Laravel') }} Admin</span>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
        <div class="dropdown" data-dropdown-root>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary dropdown-toggle"
                data-dropdown-toggle
                aria-expanded="false"
                aria-haspopup="true"
            >
                {{ auth()->user()?->name ?? 'Utilizador' }}
            </button>
            <div class="dropdown-menu dropdown-menu-end" data-dropdown-menu>
                <a class="dropdown-item" href="#">Perfil</a>
                <a class="dropdown-item" href="#">Preferências</a>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item">Terminar sessão</button>
                </form>
            </div>
        </div>
    </div>
</header>
