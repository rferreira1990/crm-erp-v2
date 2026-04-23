@php
    $user = auth()->user();
    $isSuperAdmin = $user?->isSuperAdmin() === true;

    $commercialOpen = request()->routeIs(
        'admin.customers.*',
        'admin.suppliers.*',
        'admin.quotes.*'
    );

    $articlesOpen = request()->routeIs(
        'admin.articles.*',
        'admin.brands.*',
        'admin.product-families.*'
    );

    $purchasesOpen = request()->routeIs(
        'admin.rfqs.*',
        'admin.purchase-orders.*',
        'admin.purchase-order-receipts.*',
        'admin.stock-movements.*'
    );

    $tablesOpen = request()->routeIs(
        'admin.units.*',
        'admin.categories.*',
        'admin.payment-methods.*',
        'admin.payment-terms.*',
        'admin.price-tiers.*',
        'admin.vat-rates.*',
        'admin.vat-exemption-reasons.*'
    );

    $hasPurchasesMenu = $user?->can('company.rfq.view')
        || $user?->can('company.purchase_orders.view')
        || $user?->can('company.purchase_order_receipts.view')
        || $user?->can('company.stock_movements.view');

    $hasArticlesMenu = $user?->can('company.articles.view')
        || $user?->can('company.brands.view')
        || $user?->can('company.product_families.view');
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
                        <button
                            type="button"
                            class="nav-link sidebar-submenu-toggle w-100 border-0 bg-transparent d-flex align-items-center {{ $commercialOpen ? 'active' : '' }}"
                            data-bs-toggle="collapse"
                            data-bs-target="#sidebarCommercialMenu"
                            aria-expanded="{{ $commercialOpen ? 'true' : 'false' }}"
                            aria-controls="sidebarCommercialMenu"
                        >
                            <span class="nav-link-icon"><span data-feather="briefcase"></span></span>
                            <span class="nav-link-text">Comercial</span>
                            <span class="ms-auto sidebar-submenu-chevron"><span data-feather="chevron-down"></span></span>
                        </button>
                        <div class="collapse sidebar-submenu {{ $commercialOpen ? 'show' : '' }}" id="sidebarCommercialMenu" data-bs-parent="#navbarVerticalNav">
                            <ul class="nav flex-column ms-4 mt-1 sidebar-submenu-list">
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}">Clientes</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}" href="{{ route('admin.suppliers.index') }}">Fornecedores</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.quotes.*') ? 'active' : '' }}" href="{{ route('admin.quotes.index') }}">Orcamentos</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.quotes.dashboard') ? 'active' : '' }}" href="{{ route('admin.quotes.dashboard') }}">Dashboard Orcamentos</a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    @if ($hasArticlesMenu)
                        <li class="nav-item">
                            <button
                                type="button"
                                class="nav-link sidebar-submenu-toggle w-100 border-0 bg-transparent d-flex align-items-center {{ $articlesOpen ? 'active' : '' }}"
                                data-bs-toggle="collapse"
                                data-bs-target="#sidebarArticlesMenu"
                                aria-expanded="{{ $articlesOpen ? 'true' : 'false' }}"
                                aria-controls="sidebarArticlesMenu"
                            >
                                <span class="nav-link-icon"><span data-feather="package"></span></span>
                                <span class="nav-link-text">Artigos</span>
                                <span class="ms-auto sidebar-submenu-chevron"><span data-feather="chevron-down"></span></span>
                            </button>
                            <div class="collapse sidebar-submenu {{ $articlesOpen ? 'show' : '' }}" id="sidebarArticlesMenu" data-bs-parent="#navbarVerticalNav">
                                <ul class="nav flex-column ms-4 mt-1 sidebar-submenu-list">
                                    @can('company.articles.view')
                                        <li class="nav-item">
                                            <a class="nav-link py-1 {{ request()->routeIs('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}">Artigos</a>
                                        </li>
                                    @endcan
                                    @can('company.brands.view')
                                        <li class="nav-item">
                                            <a class="nav-link py-1 {{ request()->routeIs('admin.brands.*') ? 'active' : '' }}" href="{{ route('admin.brands.index') }}">Marcas</a>
                                        </li>
                                    @endcan
                                    @can('company.product_families.view')
                                        <li class="nav-item">
                                            <a class="nav-link py-1 {{ request()->routeIs('admin.product-families.*') ? 'active' : '' }}" href="{{ route('admin.product-families.index') }}">Familias</a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </li>
                    @endif

                    @can('company.construction_sites.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.construction-sites.*') ? 'active' : '' }}" href="{{ route('admin.construction-sites.index') }}">
                                <div class="d-flex align-items-center">
                                    <span class="nav-link-icon"><span data-feather="tool"></span></span>
                                    <span class="nav-link-text">Obras</span>
                                </div>
                            </a>
                        </li>
                    @endcan

                    @if ($hasPurchasesMenu)
                        <li class="nav-item">
                            <button
                                type="button"
                                class="nav-link sidebar-submenu-toggle w-100 border-0 bg-transparent d-flex align-items-center {{ $purchasesOpen ? 'active' : '' }}"
                                data-bs-toggle="collapse"
                                data-bs-target="#sidebarPurchasesMenu"
                                aria-expanded="{{ $purchasesOpen ? 'true' : 'false' }}"
                                aria-controls="sidebarPurchasesMenu"
                            >
                                <span class="nav-link-icon"><span data-feather="shopping-cart"></span></span>
                                <span class="nav-link-text">Compras</span>
                                <span class="ms-auto sidebar-submenu-chevron"><span data-feather="chevron-down"></span></span>
                            </button>
                            <div class="collapse sidebar-submenu {{ $purchasesOpen ? 'show' : '' }}" id="sidebarPurchasesMenu" data-bs-parent="#navbarVerticalNav">
                                <ul class="nav flex-column ms-4 mt-1 sidebar-submenu-list">
                                    @can('company.rfq.view')
                                        <li class="nav-item">
                                            <a class="nav-link py-1 {{ request()->routeIs('admin.rfqs.*') ? 'active' : '' }}" href="{{ route('admin.rfqs.index') }}">Pedidos de Cotacao</a>
                                        </li>
                                    @endcan
                                    @can('company.purchase_orders.view')
                                        <li class="nav-item">
                                            <a class="nav-link py-1 {{ request()->routeIs('admin.purchase-orders.*') ? 'active' : '' }}" href="{{ route('admin.purchase-orders.index') }}">Encomendas Fornecedor</a>
                                        </li>
                                    @endcan
                                    @can('company.purchase_order_receipts.view')
                                        <li class="nav-item">
                                            <a class="nav-link py-1 {{ request()->routeIs('admin.purchase-order-receipts.*') ? 'active' : '' }}" href="{{ route('admin.purchase-order-receipts.index') }}">Rececoes de Material</a>
                                        </li>
                                    @endcan
                                    @can('company.stock_movements.view')
                                        <li class="nav-item">
                                            <a class="nav-link py-1 {{ request()->routeIs('admin.stock-movements.*') ? 'active' : '' }}" href="{{ route('admin.stock-movements.index') }}">Movimentos de Stock</a>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </li>
                    @endif

                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <div class="d-flex align-items-center">
                                <span class="nav-link-icon"><span data-feather="credit-card"></span></span>
                                <span class="nav-link-text">Faturas</span>
                            </div>
                        </a>
                    </li>

                    <li class="nav-item mt-3">
                        <p class="navbar-vertical-label">Definicoes</p>
                        <hr class="navbar-vertical-line" />
                    </li>

                    @can('company.settings.manage')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.company-settings.*') ? 'active' : '' }}" href="{{ route('admin.company-settings.edit') }}">
                                <div class="d-flex align-items-center">
                                    <span class="nav-link-icon"><span data-feather="building"></span></span>
                                    <span class="nav-link-text">Empresa</span>
                                </div>
                            </a>
                        </li>
                    @endcan

                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link sidebar-submenu-toggle w-100 border-0 bg-transparent d-flex align-items-center {{ $tablesOpen ? 'active' : '' }}"
                            data-bs-toggle="collapse"
                            data-bs-target="#sidebarTablesMenu"
                            aria-expanded="{{ $tablesOpen ? 'true' : 'false' }}"
                            aria-controls="sidebarTablesMenu"
                        >
                            <span class="nav-link-icon"><span data-feather="table"></span></span>
                            <span class="nav-link-text">Tabelas</span>
                            <span class="ms-auto sidebar-submenu-chevron"><span data-feather="chevron-down"></span></span>
                        </button>
                        <div class="collapse sidebar-submenu {{ $tablesOpen ? 'show' : '' }}" id="sidebarTablesMenu" data-bs-parent="#navbarVerticalNav">
                            <ul class="nav flex-column ms-4 mt-1 sidebar-submenu-list">
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.units.*') ? 'active' : '' }}" href="{{ route('admin.units.index') }}">Unidades</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">Categorias de produtos</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.payment-methods.*') ? 'active' : '' }}" href="{{ route('admin.payment-methods.index') }}">Modos de pagamento</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.payment-terms.*') ? 'active' : '' }}" href="{{ route('admin.payment-terms.index') }}">Condicoes de pagamento</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.price-tiers.*') ? 'active' : '' }}" href="{{ route('admin.price-tiers.index') }}">Escaloes de preco</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.vat-rates.*') ? 'active' : '' }}" href="{{ route('admin.vat-rates.index') }}">Taxas de IVA</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1 {{ request()->routeIs('admin.vat-exemption-reasons.*') ? 'active' : '' }}" href="{{ route('admin.vat-exemption-reasons.index') }}">Motivos de isencao IVA</a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.users.*', 'admin.user-invitations.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
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
