@extends('layouts.admin')

@section('title', 'Ligacao Telegram')
@section('page_title', 'Ligacao Telegram')
@section('page_subtitle', 'Ligue a sua conta Telegram ao utilizador atual do ERP')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Ligacao Telegram</li>
    </ol>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->has('telegram_link'))
                <div class="alert alert-danger">{{ $errors->first('telegram_link') }}</div>
            @endif
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Estado da ligacao</h5>
                </div>
                <div class="card-body">
                    @if ($activeLink)
                        <div class="mb-2">
                            <span class="badge bg-success">Telegram ligado</span>
                        </div>
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Telegram user id</dt>
                            <dd class="col-sm-7">{{ $activeLink->telegram_user_id }}</dd>

                            <dt class="col-sm-5">Chat id</dt>
                            <dd class="col-sm-7">{{ $activeLink->telegram_chat_id ?: '-' }}</dd>

                            <dt class="col-sm-5">Ligado em</dt>
                            <dd class="col-sm-7">{{ optional($activeLink->linked_at)->format('d/m/Y H:i') ?: '-' }}</dd>

                            <dt class="col-sm-5">Ultimo acesso</dt>
                            <dd class="col-sm-7">{{ optional($activeLink->last_seen_at)->format('d/m/Y H:i') ?: '-' }}</dd>
                        </dl>
                    @else
                        <span class="badge bg-secondary">Telegram nao ligado</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Codigo de ligacao</h5>
                </div>
                <div class="card-body">
                    @php
                        $visibleCode = session('telegram_link_code') ?: optional($activeCode)->code;
                    @endphp

                    @if ($visibleCode)
                        <div class="alert alert-info mb-3">
                            <div class="fw-semibold">Codigo ativo:</div>
                            <div class="fs-4 fw-bold">{{ $visibleCode }}</div>
                            <div class="small mt-1">
                                Expira em:
                                {{ optional($activeCode?->expires_at)->format('d/m/Y H:i') ?: '10 minutos apos geracao' }}
                            </div>
                        </div>
                    @endif

                    <p class="mb-3">No Telegram, envie:</p>
                    <div class="border rounded p-3 bg-light mb-3">
                        <code>/link CODIGO</code>
                    </div>

                    <form method="POST" action="{{ route('admin.telegram.link.code') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            Gerar codigo de ligacao
                        </button>
                    </form>

                    @if ($activeLink)
                        <form method="POST" action="{{ route('admin.telegram.link.destroy') }}" class="d-inline ms-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger">
                                Desligar Telegram
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
