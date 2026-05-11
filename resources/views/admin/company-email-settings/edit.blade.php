@extends('layouts.admin')

@section('title', 'Email da Empresa')
@section('page_title', 'Email da Empresa')
@section('page_subtitle', 'Configuracao SMTP para envio de emails do ERP')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Email da empresa</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.company-email-settings.update') }}" class="mb-4">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Servidor de email</h5>
            </div>
            <div class="card-body">
                @php
                    $usesCustomSmtp = old('mail_use_custom_settings', $company->mail_use_custom_settings) ? true : false;
                @endphp
                <div class="mb-3">
                    <label class="form-label d-block">Modo de envio</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mail_use_custom_settings" id="mail_mode_default" value="0" @checked(! $usesCustomSmtp)>
                        <label class="form-check-label" for="mail_mode_default">Conta FORTISCASA (default)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="mail_use_custom_settings" id="mail_mode_custom" value="1" @checked($usesCustomSmtp)>
                        <label class="form-check-label" for="mail_mode_custom">Conta propria SMTP</label>
                    </div>
                </div>

                <div id="customSmtpFields" class="row g-3 @if (! $usesCustomSmtp) d-none @endif">
                    <div class="col-12 col-md-6">
                        <label for="mail_from_name" class="form-label">Nome do remetente</label>
                        <input type="text" id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', $company->mail_from_name) }}" class="form-control @error('mail_from_name') is-invalid @enderror">
                        @error('mail_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="mail_from_address" class="form-label">Email de envio</label>
                        <input type="email" id="mail_from_address" name="mail_from_address" value="{{ old('mail_from_address', $company->mail_from_address) }}" class="form-control @error('mail_from_address') is-invalid @enderror">
                        @error('mail_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="mail_host" class="form-label">Host SMTP</label>
                        <input type="text" id="mail_host" name="mail_host" value="{{ old('mail_host', $company->mail_host) }}" class="form-control @error('mail_host') is-invalid @enderror">
                        @error('mail_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="mail_port" class="form-label">Porta SMTP</label>
                        <input type="number" id="mail_port" name="mail_port" value="{{ old('mail_port', $company->mail_port) }}" class="form-control @error('mail_port') is-invalid @enderror" min="1" max="65535">
                        @error('mail_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="mail_encryption" class="form-label">Encriptacao</label>
                        <select id="mail_encryption" name="mail_encryption" class="form-select @error('mail_encryption') is-invalid @enderror">
                            <option value="">Selecionar</option>
                            @foreach ($mailEncryptionOptions as $encryptionKey => $encryptionLabel)
                                <option value="{{ $encryptionKey }}" @selected(old('mail_encryption', $company->mail_encryption ?? 'none') === $encryptionKey)>{{ $encryptionLabel }}</option>
                            @endforeach
                        </select>
                        @error('mail_encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="mail_username" class="form-label">Username SMTP</label>
                        <input type="text" id="mail_username" name="mail_username" value="{{ old('mail_username', $company->mail_username) }}" class="form-control @error('mail_username') is-invalid @enderror">
                        @error('mail_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="mail_password" class="form-label">Password SMTP</label>
                        <input type="password" id="mail_password" name="mail_password" value="" class="form-control @error('mail_password') is-invalid @enderror" autocomplete="new-password">
                        <div class="form-text">Deixe vazio para manter a password atual.</div>
                        @error('mail_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                @error('smtp_test')
                    <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Guardar configuracoes de email</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Teste SMTP</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.company-email-settings.test-smtp') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-12 col-lg-8">
                    <label for="test_email" class="form-label">Email de teste (opcional)</label>
                    <input type="email" id="test_email" name="test_email" value="{{ old('test_email') }}" class="form-control @error('test_email') is-invalid @enderror" placeholder="Se vazio, usa o email da empresa ou do utilizador autenticado">
                    @error('test_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-lg-4 d-grid">
                    <button type="submit" class="btn btn-phoenix-secondary">Enviar email de teste</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const modeDefault = document.getElementById('mail_mode_default');
            const modeCustom = document.getElementById('mail_mode_custom');
            const customFields = document.getElementById('customSmtpFields');

            if (!modeDefault || !modeCustom || !customFields) {
                return;
            }

            const syncSmtpVisibility = () => {
                customFields.classList.toggle('d-none', !modeCustom.checked);
            };

            modeDefault.addEventListener('change', syncSmtpVisibility);
            modeCustom.addEventListener('change', syncSmtpVisibility);
            syncSmtpVisibility();
        })();
    </script>
@endpush

