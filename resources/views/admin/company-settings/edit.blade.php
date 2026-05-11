@extends('layouts.admin')

@section('title', 'Configuracao da Empresa')
@section('page_title', 'Configuracao da Empresa')
@section('page_subtitle', 'Dados institucionais e bancarios')

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
                    <div class="col-12 col-md-4">
                        <label for="pdf_layout" class="form-label">Layout PDF</label>
                        <select id="pdf_layout" name="pdf_layout" class="form-select @error('pdf_layout') is-invalid @enderror">
                            @foreach ($pdfLayoutOptions as $layoutKey => $layoutLabel)
                                <option value="{{ $layoutKey }}" @selected(old('pdf_layout', $company->pdf_layout ?? 'classic') === $layoutKey)>{{ $layoutLabel }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Define o estilo visual usado nos PDFs dos documentos.</div>
                        @error('pdf_layout')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Guardar configuracoes</button>
        </div>
    </form>
@endsection
