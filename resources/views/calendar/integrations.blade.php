@extends('layouts.admin')

@section('title', 'Agenda CalDAV')
@section('page_title', 'Integracao CalDAV')
@section('page_subtitle', 'Configuracao de sincronizacao da agenda da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.calendar.index') }}">Agenda</a></li>
        <li class="breadcrumb-item active" aria-current="page">CalDAV</li>
    </ol>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Calendario principal da empresa (CalDAV)</h5>
                    <div class="d-flex align-items-center gap-2">
                        @if ($integration)
                            <span class="badge {{ $integration->is_active ? 'badge-phoenix-success' : 'badge-phoenix-secondary' }}">
                                {{ $integration->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        @else
                            <span class="badge badge-phoenix-warning">Nao configurado</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    @if ($errors->has('integration_test'))
                        <div class="alert alert-danger">{{ $errors->first('integration_test') }}</div>
                    @endif

                    @if ($errors->has('integration_sync'))
                        <div class="alert alert-danger">{{ $errors->first('integration_sync') }}</div>
                    @endif

                    <form method="post" action="{{ route('admin.calendar.integrations.update') }}" class="row g-3">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="provider" value="caldav">

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="name">Nome da integracao</label>
                            <input
                                type="text"
                                class="form-control @error('name') is-invalid @enderror"
                                id="name"
                                name="name"
                                value="{{ old('name', $integration?->name ?? 'Calendario empresa') }}"
                                maxlength="120"
                                required
                            >
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="user_id">Calendario alvo</label>
                            <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id">
                                <option value="">Empresa (principal)</option>
                                @foreach ($activeUsers as $activeUser)
                                    <option value="{{ $activeUser->id }}" @selected((int) old('user_id', $integration?->user_id) === (int) $activeUser->id)>
                                        {{ $activeUser->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="username">Username</label>
                            <input
                                type="text"
                                class="form-control @error('username') is-invalid @enderror"
                                id="username"
                                name="username"
                                value="{{ old('username', $integration?->username) }}"
                                maxlength="190"
                                required
                            >
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <input
                                type="password"
                                class="form-control @error('password') is-invalid @enderror"
                                id="password"
                                name="password"
                                maxlength="255"
                                autocomplete="new-password"
                            >
                            <small class="text-body-secondary">Deixe vazio para manter a password atual.</small>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="base_url">Server URL</label>
                            <input
                                type="url"
                                class="form-control @error('base_url') is-invalid @enderror"
                                id="base_url"
                                name="base_url"
                                value="{{ old('base_url', $integration?->base_url) }}"
                                maxlength="255"
                                placeholder="https://mail.seudominio.pt:2080"
                                required
                            >
                            @error('base_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="calendar_url">Calendar URL</label>
                            <input
                                type="url"
                                class="form-control @error('calendar_url') is-invalid @enderror"
                                id="calendar_url"
                                name="calendar_url"
                                value="{{ old('calendar_url', $integration?->calendar_url) }}"
                                maxlength="255"
                                placeholder="https://mail.seudominio.pt:2080/calendars/user/calendar"
                                required
                            >
                            @error('calendar_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked((bool) old('is_active', $integration?->is_active ?? true))>
                                <label class="form-check-label" for="is_active">Integracao ativa</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="sync_enabled" name="sync_enabled" value="1" @checked((bool) old('sync_enabled', $integration?->sync_enabled ?? true))>
                                <label class="form-check-label" for="sync_enabled">Sincronizacao ativa</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Guardar configuracao</button>
                        </div>
                    </form>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <form method="post" action="{{ route('admin.calendar.integrations.test-connection') }}">
                            @csrf
                            <button type="submit" class="btn btn-phoenix-secondary">Testar ligacao</button>
                        </form>

                        <form method="post" action="{{ route('admin.calendar.integrations.sync-now') }}">
                            @csrf
                            <button type="submit" class="btn btn-phoenix-info">Sincronizar agora</button>
                        </form>
                    </div>

                    <hr class="my-4">

                    <div class="row g-2 fs-9">
                        <div class="col-12 col-md-6">
                            <strong>Ultimo sync:</strong>
                            {{ $integration?->last_sync_at?->format('d/m/Y H:i') ?? '-' }}
                        </div>
                        <div class="col-12 col-md-6">
                            <strong>Provider:</strong>
                            {{ strtoupper((string) ($integration?->provider ?? 'caldav')) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
