@extends('layouts.admin')

@section('title', 'Superadmin - Nova empresa')
@section('page_title', 'Nova empresa')
@section('page_subtitle', 'Criar empresa na plataforma')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('superadmin.home') }}">Superadmin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('superadmin.companies.index') }}">Empresas</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nova</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Dados da empresa</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.companies.store') }}" class="row g-3">
                @csrf

                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Nome</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug') }}" class="form-control @error('slug') is-invalid @enderror" required>
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-4">
                    <label for="nif" class="form-label">NIF</label>
                    <input type="text" id="nif" name="nif" value="{{ old('nif') }}" class="form-control @error('nif') is-invalid @enderror">
                    @error('nif')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-4">
                    <label for="phone" class="form-label">Telefone</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" @checked(old('is_active', true))>
                        <label class="form-check-label" for="is_active">
                            Empresa ativa
                        </label>
                    </div>
                    @error('is_active')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('superadmin.companies.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Criar empresa</button>
                </div>
            </form>
        </div>
    </div>
@endsection
