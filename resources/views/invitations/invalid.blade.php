<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" data-navigation-type="default" data-navbar-horizontal-shape="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Convite indisponivel - {{ config('app.name', 'Laravel') }}</title>

    <script src="{{ asset('vendor/phoenix/vendors/simplebar/simplebar.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/js/config.js') }}"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="{{ asset('vendor/phoenix/vendors/simplebar/simplebar.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/phoenix/css/theme.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/phoenix/css/user.min.css') }}" rel="stylesheet">
</head>
<body>
    <main class="main" id="top">
        <div class="container">
            <div class="row flex-center min-vh-100 py-5">
                <div class="col-sm-10 col-md-8 col-lg-5 col-xl-5 col-xxl-4">
                    <a class="d-flex flex-center text-decoration-none mb-4" href="{{ url('/') }}">
                        <div class="d-flex align-items-center fw-bolder fs-3 d-inline-block">
                            <img
                                src="{{ asset('assets/branding/platform-logo.png') }}"
                                alt="SmokyTech"
                                style="width: min(340px, 82vw); height: auto;"
                            >
                        </div>
                    </a>

                    <div class="text-center mb-7">
                        <h3 class="text-body-highlight">Convite indisponivel</h3>
                        <p class="text-body-tertiary mb-0">Nao foi possivel validar este convite.</p>
                    </div>

                    <div class="border border-warning-subtle rounded-3 bg-warning-subtle p-3 mb-4 text-start">
                        <div class="d-flex align-items-start gap-2">
                            <span class="fas fa-triangle-exclamation text-warning mt-1"></span>
                            <div class="fs-9 text-body-emphasis">
                                {{ $message ?? 'Este convite nao e valido ou ja nao esta disponivel.' }}
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="{{ route('login') }}" class="btn btn-primary">Iniciar sessao</a>
                        <a href="{{ url('/') }}" class="btn btn-phoenix-secondary">Voltar ao inicio</a>
                    </div>

                    <div class="text-center mt-4">
                        <small class="text-body-tertiary fs-10">Se precisar de acesso, contacte o administrador da sua empresa.</small>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="{{ asset('vendor/phoenix/vendors/popper/popper.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/bootstrap/bootstrap.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/is/is.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/fontawesome/all.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/lodash/lodash.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/list.js/list.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/feather-icons/feather.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/dayjs/dayjs.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/js/phoenix.js') }}"></script>
</body>
</html>
