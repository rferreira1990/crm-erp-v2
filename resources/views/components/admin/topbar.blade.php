@php
    $currentUser = auth()->user();
    $isSuperAdmin = $currentUser?->isSuperAdmin() === true;
    $homeRoute = $isSuperAdmin ? route('superadmin.companies.index') : route('admin.dashboard');
    $displayName = $currentUser?->name ?? 'Utilizador';
    $displayEmail = $currentUser?->email ?? 'sem-email@example.com';
    $displayInitial = strtoupper(substr($displayName ?: 'U', 0, 1));
    $photoUrl = data_get($currentUser, 'profile_photo_url');
@endphp

<nav class="navbar navbar-top fixed-top navbar-expand" id="navbarDefault">
    <div class="collapse navbar-collapse justify-content-between">
        <div class="navbar-logo">
            <button
                class="btn navbar-toggler navbar-toggler-humburger-icon hover-bg-transparent"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarVerticalCollapse"
                aria-controls="navbarVerticalCollapse"
                aria-expanded="false"
                aria-label="Toggle Navigation"
            >
                <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
            </button>
            <a class="navbar-brand me-1 me-sm-3" href="{{ $homeRoute }}">
                <div class="d-flex align-items-center">
                    <img src="{{ asset('vendor/phoenix/img/icons/logo.png') }}" alt="phoenix" width="27" />
                    <h5 class="logo-text ms-2 d-none d-sm-block">{{ config('app.name', 'CRM/ERP') }}</h5>
                </div>
            </a>
        </div>

        <ul class="navbar-nav navbar-nav-icons flex-row align-items-center">
            @if ($isSuperAdmin)
                <li class="nav-item me-3 d-none d-md-block">
                    <span class="badge badge-phoenix badge-phoenix-warning">Superadmin</span>
                </li>
            @endif

            <li class="nav-item dropdown">
                <a class="nav-link lh-1 pe-0" id="navbarDropdownUser" href="#" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
                    <div class="avatar avatar-l">
                        @if ($photoUrl)
                            <img class="rounded-circle" src="{{ $photoUrl }}" alt="{{ $displayName }}" />
                        @else
                            <div class="avatar-name rounded-circle"><span>{{ $displayInitial }}</span></div>
                        @endif
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end navbar-dropdown-caret py-0 dropdown-profile shadow border" aria-labelledby="navbarDropdownUser">
                    <div class="card position-relative border-0">
                        <div class="card-body p-0">
                            <div class="text-center pt-4 pb-3">
                                <div class="avatar avatar-xl">
                                    @if ($photoUrl)
                                        <img class="rounded-circle" src="{{ $photoUrl }}" alt="{{ $displayName }}" />
                                    @else
                                        <div class="avatar-name rounded-circle"><span>{{ $displayInitial }}</span></div>
                                    @endif
                                </div>
                                <h6 class="mt-2 text-body-emphasis mb-1">{{ $displayName }}</h6>
                                <p class="text-body-tertiary fs-10 mb-0">{{ $displayEmail }}</p>
                            </div>
                        </div>

                        <div class="overflow-auto scrollbar">
                            <ul class="nav d-flex flex-column mb-2 pb-1">
                                <li class="nav-item">
                                    <a class="nav-link px-3 d-block" href="{{ $homeRoute }}">
                                        <span class="me-2 text-body align-bottom" data-feather="home"></span>
                                        <span>{{ $isSuperAdmin ? 'Area Superadmin' : 'Dashboard' }}</span>
                                    </a>
                                </li>
                                @if ($isSuperAdmin)
                                    <li class="nav-item">
                                        <a class="nav-link px-3 d-block" href="{{ route('superadmin.companies.index') }}">
                                            <span class="me-2 text-body align-bottom" data-feather="briefcase"></span>
                                            <span>Empresas</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link px-3 d-block" href="{{ route('superadmin.invitations.index') }}">
                                            <span class="me-2 text-body align-bottom" data-feather="mail"></span>
                                            <span>Convites</span>
                                        </a>
                                    </li>
                                @endif
                                @if (Route::has('profile.edit'))
                                    <li class="nav-item">
                                        <a class="nav-link px-3 d-block" href="{{ route('profile.edit') }}">
                                            <span class="me-2 text-body align-bottom" data-feather="user"></span>
                                            <span>Perfil</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>

                        <div class="card-footer p-0 border-top border-translucent">
                            <div class="px-3 py-3">
                                @if (Route::has('logout'))
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-phoenix-secondary d-flex flex-center w-100">
                                            <span class="me-2" data-feather="log-out"></span>Sair
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</nav>
