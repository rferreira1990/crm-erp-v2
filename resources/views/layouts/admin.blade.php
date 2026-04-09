<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="CRM/ERP Admin">
    <title>@yield('title', 'Dashboard') - {{ config('app.name', 'Laravel') }}</title>

    {{-- Phoenix vendor styles (public/vendor/phoenix) --}}
    <link rel="stylesheet" href="{{ asset('vendor/phoenix/assets/css/theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/phoenix/assets/css/user.min.css') }}">

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @stack('styles')
</head>
<body class="admin-layout">
    <div class="admin-shell" id="adminShell">
        <x-admin.sidebar />

        <div class="admin-content">
            <x-admin.topbar />

            <main class="admin-main container-fluid py-4" role="main">
                <header class="admin-page-header mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h1 class="h3 mb-1">@yield('page_title', 'Dashboard')</h1>
                            @hasSection('page_subtitle')
                                <p class="text-body-secondary mb-0">@yield('page_subtitle')</p>
                            @endif
                        </div>
                        @hasSection('page_actions')
                            <div class="admin-page-actions">
                                @yield('page_actions')
                            </div>
                        @endif
                    </div>

                    @hasSection('breadcrumbs')
                        <nav aria-label="breadcrumb" class="mt-3">
                            @yield('breadcrumbs')
                        </nav>
                    @endif
                </header>

                @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                @yield('content')
            </main>

            <x-admin.footer />
        </div>
    </div>

    {{-- Phoenix vendor scripts (public/vendor/phoenix) --}}
    <script src="{{ asset('vendor/phoenix/assets/js/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('vendor/phoenix/assets/js/phoenix.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
