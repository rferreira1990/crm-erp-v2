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
                <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5 col-xxl-4">
                    <a class="d-flex flex-center text-decoration-none mb-4" href="{{ url('/') }}">
                        <div class="d-flex align-items-center fw-bolder fs-3 d-inline-block">
                            <img src="{{ asset('vendor/phoenix/img/icons/logo.png') }}" alt="logo" width="58">
                        </div>
                    </a>

                    <div class="text-center mb-7">
                        <h3 class="text-body-highlight">Aceitar convite</h3>
                        <p class="text-body-tertiary mb-0">Crie a sua conta para entrar na plataforma.</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 fs-9" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="border border-translucent rounded-3 p-3 p-sm-4 mb-4 bg-body-emphasis">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge badge-phoenix badge-phoenix-primary">Convite ativo</span>
                            <small class="text-body-tertiary">Perfil: {{ $invitation->role }}</small>
                        </div>
                        <div class="fs-9">
                            <div class="mb-1"><span class="text-body-tertiary">Empresa:</span> <span class="fw-semibold">{{ $invitation->company?->name ?? '-' }}</span></div>
                            <div class="mb-1"><span class="text-body-tertiary">Email:</span> <span class="fw-semibold">{{ $invitation->email }}</span></div>
                            <div><span class="text-body-tertiary">Validade:</span> <span class="fw-semibold">{{ $invitation->expires_at?->format('d/m/Y H:i') ?? '-' }}</span></div>
                        </div>
                    </div>

                    <div class="position-relative mt-4">
                        <hr class="bg-body-secondary">
                        <div class="divider-content-center">dados da conta</div>
                    </div>

                    <form method="POST" action="{{ route('invitations.accept.store') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="token" value="{{ old('token', $token) }}">

                        <div class="mb-3 text-start">
                            <label class="form-label" for="name">Nome</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Nome completo" required autocomplete="name">
                            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3 text-start">
                            <label class="form-label" for="invitation_email">Email</label>
                            <input type="email" id="invitation_email" class="form-control" value="{{ $invitation->email }}" readonly>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="password">Palavra-passe</label>
                                <div class="position-relative" data-password="data-password">
                                    <input class="form-control form-icon-input pe-6 @error('password') is-invalid @enderror" id="password" name="password" type="password" placeholder="Palavra-passe" data-password-input="data-password-input" required autocomplete="new-password">
                                    <button class="btn px-3 py-0 h-100 position-absolute top-0 end-0 fs-7 text-body-tertiary" type="button" data-password-toggle="data-password-toggle">
                                        <span class="uil uil-eye show"></span>
                                        <span class="uil uil-eye-slash hide"></span>
                                    </button>
                                </div>
                                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="password_confirmation">Confirmar palavra-passe</label>
                                <div class="position-relative" data-password="data-password">
                                    <input class="form-control form-icon-input pe-6" id="password_confirmation" name="password_confirmation" type="password" placeholder="Confirmar palavra-passe" data-password-input="data-password-input" required autocomplete="new-password">
                                    <button class="btn px-3 py-0 h-100 position-absolute top-0 end-0 fs-7 text-body-tertiary" type="button" data-password-toggle="data-password-toggle">
                                        <span class="uil uil-eye show"></span>
                                        <span class="uil uil-eye-slash hide"></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3">Criar conta e entrar</button>

                        <div class="text-center">
                            <a href="{{ route('login') }}" class="fs-9 fw-bold">Ja tem conta? Iniciar sessao</a>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <small class="text-body-tertiary fs-10">Se nao reconhece este convite, pode ignorar.</small>
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
