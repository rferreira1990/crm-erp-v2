@php
    $items = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home', 'enabled' => true],
        ['label' => 'Clientes', 'route' => null, 'icon' => 'users', 'enabled' => false],
        ['label' => 'Produtos', 'route' => null, 'icon' => 'box', 'enabled' => false],
        ['label' => 'Orçamentos', 'route' => null, 'icon' => 'file-text', 'enabled' => false],
        ['label' => 'Faturas', 'route' => null, 'icon' => 'receipt', 'enabled' => false],
        ['label' => 'Stocks', 'route' => null, 'icon' => 'archive', 'enabled' => false],
        ['label' => 'Utilizadores', 'route' => null, 'icon' => 'user-check', 'enabled' => false],
        ['label' => 'Definições', 'route' => null, 'icon' => 'settings', 'enabled' => false],
    ];
@endphp

<aside class="admin-sidebar border-end bg-body" id="adminSidebar" aria-label="Main navigation">
    <div class="admin-sidebar-brand px-3 py-3 border-bottom">
        <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center text-decoration-none">
            <span class="fw-bold fs-5">{{ config('app.name', 'Laravel') }}</span>
        </a>
        <small class="text-body-secondary">CRM/ERP</small>
    </div>

    <nav class="admin-sidebar-nav p-3">
        <p class="text-uppercase text-body-tertiary fw-semibold small mb-2">Admin</p>
        <ul class="nav flex-column gap-1" role="list">
            @foreach ($items as $item)
                @php
                    $isActive = $item['route'] && request()->routeIs($item['route']);
                    $href = $item['route'] ? route($item['route']) : '#';
                @endphp

                {{--
                    Future permissions:
                    Replace with @can('permission-name') ... @endcan when policies/permissions are ready.
                --}}
                <li class="nav-item">
                    <a
                        href="{{ $href }}"
                        class="nav-link d-flex align-items-center gap-2 {{ $isActive ? 'active' : 'text-body' }} {{ $item['enabled'] ? '' : 'is-placeholder' }}"
                        @if (! $item['enabled']) aria-disabled="true" @endif
                    >
                        <span class="admin-nav-icon" aria-hidden="true" data-icon="{{ $item['icon'] }}"></span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</aside>
