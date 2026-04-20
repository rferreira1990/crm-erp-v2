@extends('layouts.admin')

@section('title', 'Configuracao da Empresa')
@section('page_title', 'Configuracao da Empresa')
@section('page_subtitle', 'Dados institucionais, bancarios e email SMTP')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Configuracao da empresa</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.company-settings.update') }}" enctype="multipart/form-data" class="mb-4">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">1. Dados gerais</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Nome da empresa</label>
                        <input type="text" class="form-control" value="{{ $company->name }}" disabled readonly>
                        <div class="form-text">O nome da empresa nao pode ser alterado nesta area.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $company->email) }}" class="form-control @error('email') is-invalid @enderror">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="phone" class="form-label">Telefone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone', $company->phone) }}" class="form-control @error('phone') is-invalid @enderror">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="mobile" class="form-label">Telemovel</label>
                        <input type="text" id="mobile" name="mobile" value="{{ old('mobile', $company->mobile) }}" class="form-control @error('mobile') is-invalid @enderror">
                        @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="website" class="form-label">Website</label>
                        <input type="url" id="website" name="website" value="{{ old('website', $company->website) }}" class="form-control @error('website') is-invalid @enderror" placeholder="https://www.empresa.pt">
                        @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label">Morada</label>
                        <input type="text" id="address" name="address" value="{{ old('address', $company->address) }}" class="form-control @error('address') is-invalid @enderror">
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="postal_code" class="form-label">Codigo postal</label>
                        <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $company->postal_code) }}" class="form-control @error('postal_code') is-invalid @enderror" placeholder="1234-123">
                        @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="locality" class="form-label">Localidade</label>
                        <input type="text" id="locality" name="locality" value="{{ old('locality', $company->locality) }}" class="form-control @error('locality') is-invalid @enderror">
                        @error('locality')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="city" class="form-label">Cidade</label>
                        <input type="text" id="city" name="city" value="{{ old('city', $company->city) }}" class="form-control @error('city') is-invalid @enderror">
                        @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="logo" class="form-label">Logotipo</label>
                        <input type="file" id="logo" name="logo" class="form-control @error('logo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.svg">
                        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    @if ($company->logo_path)
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3">
                                <img src="{{ route('admin.company-settings.logo.show') }}" alt="Logotipo da empresa" style="max-height:64px;max-width:180px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="remove_logo" name="remove_logo" @checked(old('remove_logo'))>
                                    <label class="form-check-label" for="remove_logo">
                                        Remover logotipo atual
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">2. Dados bancarios</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="bank_name" class="form-label">Banco</label>
                        <input type="text" id="bank_name" name="bank_name" value="{{ old('bank_name', $company->bank_name) }}" class="form-control @error('bank_name') is-invalid @enderror">
                        @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="iban" class="form-label">IBAN</label>
                        <input type="text" id="iban" name="iban" value="{{ old('iban', $company->iban) }}" class="form-control @error('iban') is-invalid @enderror" placeholder="PT50000000000000000000000">
                        @error('iban')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="bic_swift" class="form-label">BIC/SWIFT</label>
                        <input type="text" id="bic_swift" name="bic_swift" value="{{ old('bic_swift', $company->bic_swift) }}" class="form-control @error('bic_swift') is-invalid @enderror">
                        @error('bic_swift')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">3. Servidor de email</h5>
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
            <button type="submit" class="btn btn-primary">Guardar configuracoes</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Teste SMTP</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.company-settings.test-smtp') }}" class="row g-3 align-items-end">
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

