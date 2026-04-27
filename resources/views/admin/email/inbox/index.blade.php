@extends('layouts.admin')

@section('title', 'Inbox')
@section('page_title', 'Caixa de Email')
@section('page_subtitle', 'Emails recebidos via IMAP')

@section('page_actions')
    @can('company.email_accounts.view')
        <a href="{{ route('admin.email-accounts.edit') }}" class="btn btn-phoenix-secondary btn-sm">Configuracao IMAP</a>
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

    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    @if (! $account)
        <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
            <span>Ainda nao existe conta IMAP configurada para esta empresa.</span>
            @can('company.email_accounts.manage')
                <a href="{{ route('admin.email-accounts.edit') }}" class="btn btn-phoenix-secondary btn-sm">Configurar</a>
            @endcan
        </div>
    @endif

    @if ($account && ! $account->is_active)
        <div class="alert alert-warning" role="alert">
            A conta IMAP esta configurada mas inativa. Ative-a na configuracao para sincronizar emails.
        </div>
    @endif

    <div class="email-container">
        <div class="row gx-lg-6 gx-3 py-3 z-2 position-sticky bg-body email-header rounded-2 mb-3">
            <div class="col-auto d-flex gap-2">
                @can('company.email_inbox.sync')
                    <form method="POST" action="{{ route('admin.email-inbox.sync') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary px-3" @disabled(! $account || ! $account->is_active)>
                            <span class="fas fa-rotate me-1"></span>Sincronizar
                        </button>
                    </form>
                @endcan
                @can('company.email_accounts.view')
                    <a class="btn btn-phoenix-secondary px-3" href="{{ route('admin.email-accounts.edit') }}">
                        <span class="fas fa-gear me-1"></span>Conta
                    </a>
                @endcan
            </div>

            <div class="col">
                <form
                    method="GET"
                    action="{{ route('admin.email-inbox.index') }}"
                    class="d-flex flex-wrap align-items-center gap-3"
                    data-live-table-form
                    data-live-table-target="#email-inbox-table-container"
                    data-live-table-endpoint="{{ route('admin.email-inbox.table') }}"
                    data-live-table-history-endpoint="{{ route('admin.email-inbox.index') }}"
                    data-live-table-immediate-fields='select, input[type="date"], input[type="checkbox"], input[data-live-table-immediate]'
                >
                    <div class="search-box flex-1" style="min-width: 260px;">
                        <div class="position-relative">
                            <input
                                class="form-control search-input search"
                                type="search"
                                id="q"
                                name="q"
                                value="{{ $filters['q'] }}"
                                placeholder="Pesquisar remetente, assunto ou snippet"
                                aria-label="Pesquisar"
                            />
                            <span class="fas fa-search search-box-icon"></span>
                        </div>
                    </div>

                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" value="1" id="unread" name="unread" @checked($filters['unread']) data-live-table-immediate>
                        <label class="form-check-label fs-9" for="unread">Nao lidos</label>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" value="1" id="has_attachments" name="has_attachments" @checked($filters['has_attachments']) data-live-table-immediate>
                        <label class="form-check-label fs-9" for="has_attachments">Com anexos</label>
                    </div>
                    <button type="submit" class="btn btn-phoenix-primary px-3">Filtrar</button>
                    <a href="{{ route('admin.email-inbox.index') }}" class="btn btn-phoenix-secondary px-3">Limpar</a>
                </form>
            </div>
        </div>

        <div class="row g-lg-6 mb-4">
            <div class="col-lg-auto">
                <div class="email-sidebar email-sidebar-width bg-body border rounded-2">
                    <div class="email-content scrollbar-overlay p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <p class="text-uppercase fs-10 text-body-tertiary text-opacity-85 mb-2 fw-bold">Mailbox</p>
                        </div>
                        <ul class="nav flex-column border-top border-translucent fs-9 vertical-nav mb-4">
                            <li class="nav-item">
                                <a class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start outline-none active"
                                   href="{{ route('admin.email-inbox.index') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="me-2 nav-icons fas fa-inbox"></span>
                                        <span class="flex-1">Inbox</span>
                                    </div>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start outline-none disabled"
                                   href="#!" tabindex="-1" aria-disabled="true">
                                    <div class="d-flex align-items-center">
                                        <span class="me-2 nav-icons fas fa-paper-plane"></span>
                                        <span class="flex-1">Enviados</span>
                                    </div>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start outline-none disabled"
                                   href="#!" tabindex="-1" aria-disabled="true">
                                    <div class="d-flex align-items-center">
                                        <span class="me-2 nav-icons fas fa-file-alt"></span>
                                        <span class="flex-1">Rascunhos</span>
                                    </div>
                                </a>
                            </li>
                        </ul>

                        <p class="text-uppercase fs-10 text-body-tertiary text-opacity-85 mb-2 fw-bold">Estado</p>
                        <ul class="nav flex-column border-top border-translucent fs-9 vertical-nav">
                            <li class="nav-item">
                                <span class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start">
                                    <span class="text-body-tertiary">Conta:</span>
                                    <span class="fw-semibold text-body">{{ $account?->name ?? '-' }}</span>
                                </span>
                            </li>
                            <li class="nav-item">
                                <span class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start">
                                    <span class="text-body-tertiary">Ultima sync:</span>
                                    <span class="fw-semibold text-body">{{ $account?->last_synced_at?->format('d/m/Y H:i') ?? '-' }}</span>
                                </span>
                            </li>
                            <li class="nav-item">
                                <span class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start">
                                    <span class="text-body-tertiary">Estado:</span>
                                    @if ($account && $account->is_active)
                                        <span class="badge badge-phoenix badge-phoenix-success ms-1">Ativa</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-warning ms-1">Inativa</span>
                                    @endif
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg">
                <div id="email-inbox-table-container">
                    @include('admin.email.inbox.partials.message-list', ['messages' => $messages])
                </div>
            </div>
        </div>
    </div>
@endsection
