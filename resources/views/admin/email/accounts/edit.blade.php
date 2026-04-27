@extends('layouts.admin')

@section('title', 'Configuracao IMAP')
@section('page_title', 'Caixa de Email')
@section('page_subtitle', 'Configuracao da conta IMAP da empresa')

@section('page_actions')
    @can('company.email_inbox.view')
        <a href="{{ route('admin.email-inbox.index') }}" class="btn btn-phoenix-secondary btn-sm">Abrir Inbox</a>
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Caixa de Email</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Conta IMAP</h5>
        </div>
        <div class="card-body">
            <form
                method="POST"
                action="{{ $account ? route('admin.email-accounts.update', $account->id) : route('admin.email-accounts.store') }}"
                class="row g-3"
            >
                @csrf
                @if ($account)
                    @method('PUT')
                @endif

                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Nome da conta</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $account?->name) }}" placeholder="Conta principal">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $account?->email) }}" placeholder="inbox@empresa.pt">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="imap_host" class="form-label">Host IMAP</label>
                    <input type="text" id="imap_host" name="imap_host" class="form-control @error('imap_host') is-invalid @enderror" value="{{ old('imap_host', $account?->imap_host) }}" placeholder="imap.exemplo.pt">
                    @error('imap_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-3">
                    <label for="imap_port" class="form-label">Porta IMAP</label>
                    <input type="number" id="imap_port" name="imap_port" min="1" max="65535" class="form-control @error('imap_port') is-invalid @enderror" value="{{ old('imap_port', $account?->imap_port ?? 993) }}">
                    @error('imap_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-3">
                    <label for="imap_encryption" class="form-label">Encriptacao</label>
                    <select id="imap_encryption" name="imap_encryption" class="form-select @error('imap_encryption') is-invalid @enderror">
                        @foreach ($encryptionOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('imap_encryption', $account?->imap_encryption ?? 'ssl') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('imap_encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="imap_username" class="form-label">Username IMAP</label>
                    <input type="text" id="imap_username" name="imap_username" class="form-control @error('imap_username') is-invalid @enderror" value="{{ old('imap_username', $account?->imap_username) }}">
                    @error('imap_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="imap_password" class="form-label">Password IMAP</label>
                    <input type="password" id="imap_password" name="imap_password" autocomplete="new-password" class="form-control @error('imap_password') is-invalid @enderror" value="">
                    <div class="form-text">{{ $account ? 'Deixe vazio para manter a password atual.' : 'Obrigatoria na criacao da conta.' }}</div>
                    @error('imap_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="imap_folder" class="form-label">Pasta IMAP</label>
                    <input type="text" id="imap_folder" name="imap_folder" class="form-control @error('imap_folder') is-invalid @enderror" value="{{ old('imap_folder', $account?->imap_folder ?? 'INBOX') }}" placeholder="INBOX">
                    @error('imap_folder')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" @checked(old('is_active', (bool) ($account?->is_active ?? false)))>
                        <label class="form-check-label" for="is_active">
                            Conta ativa
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <hr class="my-1">
                    <h5 class="mb-0 mt-2">SMTP (igual ao Email da Empresa)</h5>
                    <div class="text-body-tertiary fs-9">Obrigatorio: a conta da Caixa de Email guarda IMAP + SMTP.</div>
                </div>

                <input type="hidden" name="smtp_use_custom_settings" value="1">

                <div id="customSmtpFields" class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="smtp_from_name" class="form-label">Nome do remetente</label>
                        <input type="text" id="smtp_from_name" name="smtp_from_name" class="form-control @error('smtp_from_name') is-invalid @enderror" value="{{ old('smtp_from_name', $account?->smtp_from_name) }}" placeholder="Empresa XYZ">
                        @error('smtp_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="smtp_from_address" class="form-label">Email de envio</label>
                        <input type="email" id="smtp_from_address" name="smtp_from_address" class="form-control @error('smtp_from_address') is-invalid @enderror" value="{{ old('smtp_from_address', $account?->smtp_from_address) }}" placeholder="geral@empresa.pt">
                        @error('smtp_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="smtp_host" class="form-label">Host SMTP</label>
                        <input type="text" id="smtp_host" name="smtp_host" class="form-control @error('smtp_host') is-invalid @enderror" value="{{ old('smtp_host', $account?->smtp_host) }}" placeholder="smtp.exemplo.pt">
                        @error('smtp_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="smtp_port" class="form-label">Porta SMTP</label>
                        <input type="number" id="smtp_port" name="smtp_port" min="1" max="65535" class="form-control @error('smtp_port') is-invalid @enderror" value="{{ old('smtp_port', $account?->smtp_port ?? 587) }}">
                        @error('smtp_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="smtp_encryption" class="form-label">Encriptacao</label>
                        <select id="smtp_encryption" name="smtp_encryption" class="form-select @error('smtp_encryption') is-invalid @enderror">
                            <option value="">Selecionar</option>
                            @foreach ($smtpEncryptionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('smtp_encryption', $account?->smtp_encryption) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('smtp_encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="smtp_username" class="form-label">Username SMTP</label>
                        <input type="text" id="smtp_username" name="smtp_username" class="form-control @error('smtp_username') is-invalid @enderror" value="{{ old('smtp_username', $account?->smtp_username) }}">
                        @error('smtp_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="smtp_password" class="form-label">Password SMTP</label>
                        <input type="password" id="smtp_password" name="smtp_password" autocomplete="new-password" class="form-control @error('smtp_password') is-invalid @enderror" value="">
                        <div class="form-text">{{ $account ? 'Deixe vazio para manter a password SMTP atual.' : 'Obrigatoria apenas se ativar SMTP proprio.' }}</div>
                        @error('smtp_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Guardar configuracao</button>
                </div>
            </form>
        </div>
    </div>

    @if ($account)
        <div class="card">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Ligacao IMAP</h5>
                <form method="POST" action="{{ route('admin.email-accounts.test-connection', $account->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-phoenix-secondary btn-sm">Testar ligacao</button>
                </form>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <div class="text-body-tertiary fs-9">Ultima sincronizacao</div>
                        <div class="fw-semibold">{{ $account->last_synced_at?->format('Y-m-d H:i') ?? '-' }}</div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="text-body-tertiary fs-9">Ultimo erro</div>
                        <div class="fw-semibold text-danger">{{ $account->last_error ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
