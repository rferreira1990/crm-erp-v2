@extends('layouts.admin')

@section('title', 'Superadmin - Definicoes de Email')
@section('page_title', 'Definicoes')
@section('page_subtitle', 'Configuracao base de email da plataforma')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('superadmin.home') }}">Superadmin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Definicoes de email</li>
    </ol>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Configuracao SMTP e branding</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.settings.email.update') }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-12">
                    <h6 class="mb-2">SMTP</h6>
                    <hr class="mt-0">
                </div>

                <div class="col-12 col-md-4">
                    <label for="mail_mailer" class="form-label">Mailer</label>
                    <select id="mail_mailer" name="mail_mailer" class="form-select @error('mail_mailer') is-invalid @enderror" required>
                        @foreach (array_keys((array) config('mail.mailers')) as $mailer)
                            <option value="{{ $mailer }}" @selected(old('mail_mailer', $settings['mail_mailer']) === $mailer)>{{ $mailer }}</option>
                        @endforeach
                    </select>
                    @error('mail_mailer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-4">
                    <label for="mail_host" class="form-label">Host SMTP</label>
                    <input type="text" id="mail_host" name="mail_host" value="{{ old('mail_host', $settings['mail_host']) }}" class="form-control @error('mail_host') is-invalid @enderror">
                    @error('mail_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-4">
                    <label for="mail_port" class="form-label">Porta SMTP</label>
                    <input type="number" id="mail_port" name="mail_port" value="{{ old('mail_port', $settings['mail_port']) }}" class="form-control @error('mail_port') is-invalid @enderror" min="1" max="65535">
                    @error('mail_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-4">
                    <label for="mail_username" class="form-label">Username SMTP</label>
                    <input type="text" id="mail_username" name="mail_username" value="{{ old('mail_username', $settings['mail_username']) }}" class="form-control @error('mail_username') is-invalid @enderror">
                    @error('mail_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-4">
                    <label for="mail_password" class="form-label">Password SMTP</label>
                    <input type="password" id="mail_password" name="mail_password" value="" class="form-control @error('mail_password') is-invalid @enderror" autocomplete="new-password">
                    <div class="form-text">Deixe vazio para manter a password atual.</div>
                    @error('mail_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-4">
                    <label for="mail_encryption" class="form-label">Encriptacao</label>
                    <select id="mail_encryption" name="mail_encryption" class="form-select @error('mail_encryption') is-invalid @enderror">
                        <option value="tls" @selected(old('mail_encryption', $settings['mail_encryption']) === 'tls')>tls</option>
                        <option value="ssl" @selected(old('mail_encryption', $settings['mail_encryption']) === 'ssl')>ssl</option>
                        <option value="null" @selected(old('mail_encryption', $settings['mail_encryption']) === 'null')>null</option>
                    </select>
                    @error('mail_encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 mt-4">
                    <h6 class="mb-2">Branding</h6>
                    <hr class="mt-0">
                </div>

                <div class="col-12 col-md-6">
                    <label for="mail_from_name" class="form-label">Nome do remetente</label>
                    <input type="text" id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', $settings['mail_from_name']) }}" class="form-control @error('mail_from_name') is-invalid @enderror" required>
                    @error('mail_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="mail_from_address" class="form-label">Email de envio</label>
                    <input type="email" id="mail_from_address" name="mail_from_address" value="{{ old('mail_from_address', $settings['mail_from_address']) }}" class="form-control @error('mail_from_address') is-invalid @enderror" required>
                    @error('mail_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="mail_reply_to" class="form-label">Reply-to (opcional)</label>
                    <input type="email" id="mail_reply_to" name="mail_reply_to" value="{{ old('mail_reply_to', $settings['mail_reply_to']) }}" class="form-control @error('mail_reply_to') is-invalid @enderror">
                    @error('mail_reply_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="app_name" class="form-label">Nome da aplicacao (opcional)</label>
                    <input type="text" id="app_name" name="app_name" value="{{ old('app_name', $settings['app_name']) }}" class="form-control @error('app_name') is-invalid @enderror">
                    @error('app_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Guardar definicoes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Repor para .env</h5>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <p class="mb-0 text-body-tertiary">Remove configuracoes guardadas em <code>mail.*</code> e volta ao fallback do .env.</p>
            <form method="POST" action="{{ route('superadmin.settings.email.reset') }}">
                @csrf
                <button type="submit" class="btn btn-phoenix-danger">Repor configuracoes de email</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Teste SMTP</h5>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <p class="mb-0 text-body-tertiary">Envia um email de teste para o utilizador autenticado com a configuracao atual.</p>
            <form method="POST" action="{{ route('superadmin.settings.email.test-smtp') }}">
                @csrf
                <button type="submit" class="btn btn-phoenix-secondary">Testar SMTP</button>
            </form>
        </div>
        @error('smtp_test')
            <div class="card-footer">
                <div class="alert alert-danger mb-0">{{ $message }}</div>
            </div>
        @enderror
    </div>
@endsection
