<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" data-navigation-type="default" data-navbar-horizontal-shape="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Aceitar convite - {{ config('app.name', 'Laravel') }}</title>

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
                <div class="col-sm-11 col-md-9 col-lg-7 col-xl-6">
                    <div class="text-center mb-6">
                        <h2 class="text-body-highlight mb-2">Aceitar convite</h2>
                        <p class="text-body-tertiary mb-0">Complete os dados para criar a sua conta.</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 fs-9" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="card border border-translucent mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Detalhes do convite</h5>
                            <div class="row g-3 fs-9">
                                <div class="col-md-6">
                                    <span class="text-body-tertiary d-block">Empresa</span>
                                    <strong>{{ $invitation->company?->name ?? '-' }}</strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-body-tertiary d-block">Perfil</span>
                                    <strong>{{ $invitation->role }}</strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-body-tertiary d-block">Email</span>
                                    <strong>{{ $invitation->email }}</strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-body-tertiary d-block">Validade</span>
                                    <strong>{{ $invitation->expires_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border border-translucent">
                        <div class="card-body">
                            <form method="POST" action="{{ route('invitations.accept.store') }}" class="row g-3">
                                @csrf
                                <input type="hidden" name="token" value="{{ old('token', $token) }}">

                                <div class="col-12">
                                    <label for="name" class="form-label">Nome</label>
                                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required autocomplete="name">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="{{ $invitation->email }}" readonly>
                                </div>

                                <div class="col-md-6">
                                    <label for="password" class="form-label">Palavra-passe</label>
                                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="password_confirmation" class="form-label">Confirmar palavra-passe</label>
                                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                                </div>

                                <div class="col-12 d-grid">
                                    <button type="submit" class="btn btn-primary">Criar conta e entrar</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="{{ route('login') }}" class="fs-9 fw-semibold">Ja tem conta? Iniciar sessao</a>
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
