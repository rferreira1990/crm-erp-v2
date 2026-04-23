<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" data-navigation-type="default" data-navbar-horizontal-shape="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar Sessão - {{ config('app.name', 'Laravel') }}</title>

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('vendor/phoenix/img/favicons/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('vendor/phoenix/img/favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('vendor/phoenix/img/favicons/favicon-16x16.png') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('vendor/phoenix/img/favicons/favicon.ico') }}">

    <script src="{{ asset('vendor/phoenix/vendors/simplebar/simplebar.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/js/config.js') }}"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="{{ asset('vendor/phoenix/vendors/simplebar/simplebar.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
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
                        <h3 class="text-body-highlight">Iniciar sessão</h3>
                        <p class="text-body-tertiary">Aceda à sua conta</p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success py-2 fs-9" role="alert">{{ session('status') }}</div>
                    @endif

                    <button type="button" class="btn btn-phoenix-secondary w-100 mb-3" disabled>
                        <span class="fab fa-google text-danger me-2 fs-9"></span>Entrar com Google
                    </button>
                    <button type="button" class="btn btn-phoenix-secondary w-100" disabled>
                        <span class="fab fa-facebook text-primary me-2 fs-9"></span>Entrar com Facebook
                    </button>

                    <div class="position-relative">
                        <hr class="bg-body-secondary mt-5 mb-4">
                        <div class="divider-content-center">ou use o email</div>
                    </div>

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3 text-start">
                            <label class="form-label" for="email">Endereço de email</label>
                            <div class="form-icon-container">
                                <input
                                    class="form-control form-icon-input @error('email') is-invalid @enderror"
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    placeholder="name@example.com"
                                    required
                                    autofocus
                                    autocomplete="username"
                                >
                                <span class="fas fa-user text-body fs-9 form-icon"></span>
                            </div>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3 text-start">
                            <label class="form-label" for="password">Palavra-passe</label>
                            <div class="form-icon-container" data-password="data-password">
                                <input
                                    class="form-control form-icon-input pe-6 @error('password') is-invalid @enderror"
                                    id="password"
                                    name="password"
                                    type="password"
                                    placeholder="Palavra-passe"
                                    required
                                    autocomplete="current-password"
                                    data-password-input="data-password-input"
                                >
                                <span class="fas fa-key text-body fs-9 form-icon"></span>
                                <button class="btn px-3 py-0 h-100 position-absolute top-0 end-0 fs-7 text-body-tertiary" type="button" data-password-toggle="data-password-toggle">
                                    <span class="uil uil-eye show"></span>
                                    <span class="uil uil-eye-slash hide"></span>
                                </button>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row flex-between-center mb-7">
                            <div class="col-auto">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" id="remember" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label mb-0" for="remember">Lembrar-me</label>
                                </div>
                            </div>
                            <div class="col-auto">
                                @if (Route::has('password.request'))
                                    <a class="fs-9 fw-semibold" href="{{ route('password.request') }}">Esqueceu-se da palavra-passe?</a>
                                @endif
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3">Entrar</button>

                        @if (Route::has('register'))
                            <div class="text-center">
                                <a class="fs-9 fw-bold" href="{{ route('register') }}">Criar conta</a>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="{{ asset('vendor/phoenix/vendors/popper/popper.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/bootstrap/bootstrap.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/anchorjs/anchor.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/is/is.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/fontawesome/all.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/lodash/lodash.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/list.js/list.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/feather-icons/feather.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/vendors/dayjs/dayjs.min.js') }}"></script>
    <script src="{{ asset('vendor/phoenix/js/phoenix.js') }}"></script>
</body>
</html>
