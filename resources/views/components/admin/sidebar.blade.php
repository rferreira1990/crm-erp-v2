@php
    $user = auth()->user();
    $isSuperAdmin = $user?->isSuperAdmin() === true;

    $commercialOpen = request()->routeIs(
        'admin.customers.*',
        'admin.suppliers.*',
        'admin.quotes.*',
        'admin.sales-documents.*',
        'admin.sales-document-receipts.*'
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

    $emailOpen = request()->routeIs(
        'admin.email-accounts.*',
        'admin.email-inbox.*',
        'admin.email-messages.*',
        'admin.email-attachments.*'
    );

    $hasPurchasesMenu = $user?->can('company.rfq.view')
        || $user?->can('company.purchase_orders.view')
        || $user?->can('company.purchase_order_receipts.view')
        || $user?->can('company.stock_movements.view');

    $hasArticlesMenu = $user?->can('company.articles.view')
        || $user?->can('company.brands.view')
        || $user?->can('company.product_families.view');

    $hasEmailMenu = $user?->can('company.email_inbox.view')
        || $user?->can('company.email_accounts.view');
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
                        <div class="nav-item-wrapper">
                            <a
                                class="nav-link dropdown-indicator label-1 {{ $commercialOpen ? 'active' : '' }}"
                                href="#sidebarCommercialMenu"
                                role="button"
                                data-bs-toggle="collapse"
                                aria-expanded="{{ $commercialOpen ? 'true' : 'false' }}"
                                aria-controls="sidebarCommercialMenu"
                            >
                                <div class="d-flex align-items-center">
                                    <div class="dropdown-indicator-icon-wrapper">
                                        <span class="fas fa-caret-right dropdown-indicator-icon"></span>
                                    </div>
                                    <span class="nav-link-icon"><span data-feather="briefcase"></span></span>
                                    <span class="nav-link-text">Comercial</span>
                                </div>
                            </a>
                            <div class="parent-wrapper label-1">
                                <ul class="nav collapse parent {{ $commercialOpen ? 'show' : '' }}" data-bs-parent="#navbarVerticalCollapse" id="sidebarCommercialMenu">
                                    <li class="collapsed-nav-item-title d-none">Comercial</li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Clientes</span></div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}" href="{{ route('admin.suppliers.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Fornecedores</span></div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.quotes.*') ? 'active' : '' }}" href="{{ route('admin.quotes.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Orcamentos</span></div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.quotes.dashboard') ? 'active' : '' }}" href="{{ route('admin.quotes.dashboard') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Dashboard Orcamentos</span></div>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </li>

                    @if ($hasArticlesMenu)
                        <li class="nav-item">
                            <div class="nav-item-wrapper">
                                <a
                                    class="nav-link dropdown-indicator label-1 {{ $articlesOpen ? 'active' : '' }}"
                                    href="#sidebarArticlesMenu"
                                    role="button"
                                    data-bs-toggle="collapse"
                                    aria-expanded="{{ $articlesOpen ? 'true' : 'false' }}"
                                    aria-controls="sidebarArticlesMenu"
                                >
                                    <div class="d-flex align-items-center">
                                        <div class="dropdown-indicator-icon-wrapper">
                                            <span class="fas fa-caret-right dropdown-indicator-icon"></span>
                                        </div>
                                        <span class="nav-link-icon"><span data-feather="package"></span></span>
                                        <span class="nav-link-text">Artigos</span>
                                    </div>
                                </a>
                                <div class="parent-wrapper label-1">
                                    <ul class="nav collapse parent {{ $articlesOpen ? 'show' : '' }}" data-bs-parent="#navbarVerticalCollapse" id="sidebarArticlesMenu">
                                        <li class="collapsed-nav-item-title d-none">Artigos</li>
                                        @can('company.articles.view')
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}">
                                                    <div class="d-flex align-items-center"><span class="nav-link-text">Artigos</span></div>
                                                </a>
                                            </li>
                                        @endcan
                                        @can('company.brands.view')
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.brands.*') ? 'active' : '' }}" href="{{ route('admin.brands.index') }}">
                                                    <div class="d-flex align-items-center"><span class="nav-link-text">Marcas</span></div>
                                                </a>
                                            </li>
                                        @endcan
                                        @can('company.product_families.view')
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.product-families.*') ? 'active' : '' }}" href="{{ route('admin.product-families.index') }}">
                                                    <div class="d-flex align-items-center"><span class="nav-link-text">Familias</span></div>
                                                </a>
                                            </li>
                                        @endcan
                                    </ul>
                                </div>
                            </div>
                        </li>
                    @endif

                    @can('company.construction_sites.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.construction-sites.*', 'admin.construction-site-time-entries.*') ? 'active' : '' }}" href="{{ route('admin.construction-sites.index') }}">
                                <div class="d-flex align-items-center">
                                    <span class="nav-link-icon"><span data-feather="tool"></span></span>
                                    <span class="nav-link-text">Obras</span>
                                </div>
                            </a>
                        </li>
                    @endcan

                    @can('company.construction_site_time_entries.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.construction-site-time-entries.*') ? 'active' : '' }}" href="{{ route('admin.construction-site-time-entries.index') }}">
                                <div class="d-flex align-items-center">
                                    <span class="nav-link-icon"><span data-feather="clock"></span></span>
                                    <span class="nav-link-text">Horas de Obra</span>
                                </div>
                            </a>
                        </li>
                    @endcan

                    @if ($hasPurchasesMenu)
                        <li class="nav-item">
                            <div class="nav-item-wrapper">
                                <a
                                    class="nav-link dropdown-indicator label-1 {{ $purchasesOpen ? 'active' : '' }}"
                                    href="#sidebarPurchasesMenu"
                                    role="button"
                                    data-bs-toggle="collapse"
                                    aria-expanded="{{ $purchasesOpen ? 'true' : 'false' }}"
                                    aria-controls="sidebarPurchasesMenu"
                                >
                                    <div class="d-flex align-items-center">
                                        <div class="dropdown-indicator-icon-wrapper">
                                            <span class="fas fa-caret-right dropdown-indicator-icon"></span>
                                        </div>
                                        <span class="nav-link-icon"><span data-feather="shopping-cart"></span></span>
                                        <span class="nav-link-text">Compras</span>
                                    </div>
                                </a>
                                <div class="parent-wrapper label-1">
                                    <ul class="nav collapse parent {{ $purchasesOpen ? 'show' : '' }}" data-bs-parent="#navbarVerticalCollapse" id="sidebarPurchasesMenu">
                                        <li class="collapsed-nav-item-title d-none">Compras</li>
                                        @can('company.rfq.view')
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.rfqs.*') ? 'active' : '' }}" href="{{ route('admin.rfqs.index') }}">
                                                    <div class="d-flex align-items-center"><span class="nav-link-text">Pedidos de Cotacao</span></div>
                                                </a>
                                            </li>
                                        @endcan
                                        @can('company.purchase_orders.view')
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.purchase-orders.*') ? 'active' : '' }}" href="{{ route('admin.purchase-orders.index') }}">
                                                    <div class="d-flex align-items-center"><span class="nav-link-text">Encomendas Fornecedor</span></div>
                                                </a>
                                            </li>
                                        @endcan
                                        @can('company.purchase_order_receipts.view')
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.purchase-order-receipts.*') ? 'active' : '' }}" href="{{ route('admin.purchase-order-receipts.index') }}">
                                                    <div class="d-flex align-items-center"><span class="nav-link-text">Rececoes de Material</span></div>
                                                </a>
                                            </li>
                                        @endcan
                                        @can('company.stock_movements.view')
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.stock-movements.*') ? 'active' : '' }}" href="{{ route('admin.stock-movements.index') }}">
                                                    <div class="d-flex align-items-center"><span class="nav-link-text">Movimentos de Stock</span></div>
                                                </a>
                                            </li>
                                        @endcan
                                    </ul>
                                </div>
                            </div>
                        </li>
                    @endif

                    @can('company.sales_documents.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.sales-documents.*') ? 'active' : '' }}" href="{{ route('admin.sales-documents.index') }}">
                                <div class="d-flex align-items-center">
                                    <span class="nav-link-icon"><span data-feather="credit-card"></span></span>
                                    <span class="nav-link-text">Documentos de Venda</span>
                                </div>
                            </a>
                        </li>
                    @endcan

                    @can('company.sales_document_receipts.view')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.sales-document-receipts.*') ? 'active' : '' }}" href="{{ route('admin.sales-document-receipts.index') }}">
                                <div class="d-flex align-items-center">
                                    <span class="nav-link-icon"><span data-feather="file-text"></span></span>
                                    <span class="nav-link-text">Recibos</span>
                                </div>
                            </a>
                        </li>
                    @endcan

                    @if ($hasEmailMenu)
                        <li class="nav-item">
                            <div class="nav-item-wrapper">
                                <a
                                    class="nav-link dropdown-indicator label-1 {{ $emailOpen ? 'active' : '' }}"
                                    href="#sidebarEmailMenu"
                                    role="button"
                                    data-bs-toggle="collapse"
                                    aria-expanded="{{ $emailOpen ? 'true' : 'false' }}"
                                    aria-controls="sidebarEmailMenu"
                                >
                                    <div class="d-flex align-items-center">
                                        <div class="dropdown-indicator-icon-wrapper">
                                            <span class="fas fa-caret-right dropdown-indicator-icon"></span>
                                        </div>
                                        <span class="nav-link-icon"><span data-feather="mail"></span></span>
                                        <span class="nav-link-text">Caixa de Email</span>
                                    </div>
                                </a>
                                <div class="parent-wrapper label-1">
                                    <ul class="nav collapse parent {{ $emailOpen ? 'show' : '' }}" data-bs-parent="#navbarVerticalCollapse" id="sidebarEmailMenu">
                                        <li class="collapsed-nav-item-title d-none">Caixa de Email</li>
                                        @can('company.email_inbox.view')
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.email-inbox.*', 'admin.email-messages.*', 'admin.email-attachments.*') ? 'active' : '' }}" href="{{ route('admin.email-inbox.index') }}">
                                                    <div class="d-flex align-items-center"><span class="nav-link-text">Inbox</span></div>
                                                </a>
                                            </li>
                                        @endcan
                                        @can('company.email_accounts.view')
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('admin.email-accounts.*') ? 'active' : '' }}" href="{{ route('admin.email-accounts.edit') }}">
                                                    <div class="d-flex align-items-center"><span class="nav-link-text">Configuracao IMAP</span></div>
                                                </a>
                                            </li>
                                        @endcan
                                    </ul>
                                </div>
                            </div>
                        </li>
                    @endif

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
                        <div class="nav-item-wrapper">
                            <a
                                class="nav-link dropdown-indicator label-1 {{ $tablesOpen ? 'active' : '' }}"
                                href="#sidebarTablesMenu"
                                role="button"
                                data-bs-toggle="collapse"
                                aria-expanded="{{ $tablesOpen ? 'true' : 'false' }}"
                                aria-controls="sidebarTablesMenu"
                            >
                                <div class="d-flex align-items-center">
                                    <div class="dropdown-indicator-icon-wrapper">
                                        <span class="fas fa-caret-right dropdown-indicator-icon"></span>
                                    </div>
                                    <span class="nav-link-icon"><span data-feather="table"></span></span>
                                    <span class="nav-link-text">Tabelas</span>
                                </div>
                            </a>
                            <div class="parent-wrapper label-1">
                                <ul class="nav collapse parent {{ $tablesOpen ? 'show' : '' }}" data-bs-parent="#navbarVerticalCollapse" id="sidebarTablesMenu">
                                    <li class="collapsed-nav-item-title d-none">Tabelas</li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.units.*') ? 'active' : '' }}" href="{{ route('admin.units.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Unidades</span></div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Categorias de produtos</span></div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.payment-methods.*') ? 'active' : '' }}" href="{{ route('admin.payment-methods.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Modos de pagamento</span></div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.payment-terms.*') ? 'active' : '' }}" href="{{ route('admin.payment-terms.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Condicoes de pagamento</span></div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.price-tiers.*') ? 'active' : '' }}" href="{{ route('admin.price-tiers.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Escaloes de preco</span></div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.vat-rates.*') ? 'active' : '' }}" href="{{ route('admin.vat-rates.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Taxas de IVA</span></div>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ request()->routeIs('admin.vat-exemption-reasons.*') ? 'active' : '' }}" href="{{ route('admin.vat-exemption-reasons.index') }}">
                                            <div class="d-flex align-items-center"><span class="nav-link-text">Motivos de isencao IVA</span></div>
                                        </a>
                                    </li>
                                </ul>
                            </div>
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
