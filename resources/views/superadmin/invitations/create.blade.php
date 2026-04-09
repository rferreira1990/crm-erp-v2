@extends('layouts.admin')

@section('title', 'Superadmin - Novo convite')
@section('page_title', 'Novo convite')
@section('page_subtitle', 'Convidar admin inicial para empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('superadmin.home') }}">Superadmin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('superadmin.invitations.index') }}">Convites</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Dados do convite</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.invitations.store') }}" class="row g-3">
                @csrf

                <div class="col-12 col-md-6">
                    <label for="company_id" class="form-label">Empresa</label>
                    <select id="company_id" name="company_id" class="form-select @error('company_id') is-invalid @enderror" required>
                        <option value="">Selecionar empresa</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected((int) old('company_id') === $company->id)>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="email" class="form-label">Email do admin</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                        <option value="company_admin" @selected(old('role', 'company_admin') === 'company_admin')>company_admin</option>
                    </select>
                    @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="expires_at" class="form-label">Expira em (opcional)</label>
                    <input type="datetime-local" id="expires_at" name="expires_at" value="{{ old('expires_at') }}" class="form-control @error('expires_at') is-invalid @enderror">
                    <div class="form-text">Se vazio, o convite expira em 7 dias.</div>
                    @error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('superadmin.invitations.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Criar convite</button>
                </div>
            </form>
        </div>
    </div>
@endsection
