@php
    $user = auth()->user();
    $isSuperAdmin = $user?->isSuperAdmin() === true;
@endphp

<nav class="navbar navbar-vertical navbar-expand-lg">
    <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
        <div class="navbar-vertical-content">
            <ul class="navbar-nav flex-column" id="navbarVerticalNav">
                @if ($isSuperAdmin)
                    <li class="nav-item">
                        <p class="navbar-vertical-label">Superadmin</p>
                        <hr class="navbar-vertical-line" />
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('superadmin.companies.*') ? 'active' : '' }}" href="{{ route('superadmin.companies.index') }}">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="briefcase"></span></span>
                                <span class="nav-link-text">Empresas</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('superadmin.invitations.*') ? 'active' : '' }}" href="{{ route('superadmin.invitations.index') }}">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="mail"></span></span>
                                <span class="nav-link-text">Convites</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <p class="navbar-vertical-label">Definicoes</p>
                        <hr class="navbar-vertical-line" />
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('superadmin.settings.email.*') ? 'active' : '' }}" href="{{ route('superadmin.settings.email.edit') }}">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="settings"></span></span>
                                <span class="nav-link-text">Email</span>
                            </div>
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <p class="navbar-vertical-label">Administracao</p>
                        <hr class="navbar-vertical-line" />
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="pie-chart"></span></span>
                                <span class="nav-link-text">Dashboard</span>
                            </div>
                        </a>
                    </li>

                    <li class="nav-item mt-3">
                        <p class="navbar-vertical-label">Modulos</p>
                        <hr class="navbar-vertical-line" />
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="users"></span></span>
                                <span class="nav-link-text">Clientes</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="package"></span></span>
                                <span class="nav-link-text">Produtos</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="file-text"></span></span>
                                <span class="nav-link-text">Orcamentos</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="credit-card"></span></span>
                                <span class="nav-link-text">Faturas</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="archive"></span></span>
                                <span class="nav-link-text">Stocks</span>
                            </div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="user-check"></span></span>
                                <span class="nav-link-text">Utilizadores</span>
                            </div>
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    </div>

    <div class="navbar-vertical-footer">
        <button class="btn navbar-vertical-toggle border-0 fw-semibold w-100 white-space-nowrap d-flex align-items-center" type="button">
            <span class="uil uil-left-arrow-to-left fs-8"></span>
            <span class="uil uil-arrow-from-right fs-8"></span>
            <span class="navbar-vertical-footer-text ms-2">Collapsed View</span>
        </button>
    </div>
</nav>
