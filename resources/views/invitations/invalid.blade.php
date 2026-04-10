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
                <div class="col-sm-11 col-md-8 col-lg-6">
                    <div class="card border border-translucent text-center">
                        <div class="card-body py-6 px-4">
                            <h3 class="text-body-highlight mb-3">Convite indisponivel</h3>
                            <p class="text-body-tertiary mb-4">{{ $message ?? 'Este convite nao e valido ou ja nao esta disponivel.' }}</p>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="{{ route('login') }}" class="btn btn-primary">Iniciar sessao</a>
                                <a href="{{ url('/') }}" class="btn btn-phoenix-secondary">Voltar ao inicio</a>
                            </div>
                        </div>
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
