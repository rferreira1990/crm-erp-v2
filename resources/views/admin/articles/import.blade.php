@extends('layouts.admin')

@section('title', 'Importar artigos CSV')
@section('page_title', 'Importar artigos CSV')
@section('page_subtitle', 'Upload e processamento de ficheiro CSV')

@section('page_actions')
    <a href="{{ route('admin.articles.import.template.csv') }}" class="btn btn-phoenix-secondary btn-sm">Download template CSV</a>
    <a href="{{ route('admin.articles.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar a artigos</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.articles.index') }}">Artigos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Importar CSV</li>
    </ol>
@endsection

@section('content')
    @php
        $summary = session('importSummary');
    @endphp

    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Importacao CSV</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.articles.import.csv') }}" enctype="multipart/form-data" class="row g-3">
                @csrf

                <div class="col-12 col-md-8">
                    <label for="csv_file" class="form-label">Ficheiro CSV</label>
                    <input
                        type="file"
                        id="csv_file"
                        name="csv_file"
                        accept=".csv,.txt"
                        class="form-control @error('csv_file') is-invalid @enderror"
                        required
                    >
                    @error('csv_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-body-tertiary">
                        Delimitador recomendado: <code>;</code>. Cabecalhos suportados:
                        <code>reference;name;description;family;brand;unit;cost_price;sale_price;is_active;stock_current;stock_ordered_pending</code>.
                    </small>
                </div>

                <div class="col-12 col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Processar importacao</button>
                </div>
            </form>
        </div>
    </div>

    @if (is_array($summary))
        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Resumo da importacao</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                        <thead class="bg-body-tertiary">
                            <tr>
                                <th class="ps-3">Processadas</th>
                                <th>Criadas</th>
                                <th>Atualizadas</th>
                                <th>Familias criadas</th>
                                <th>Marcas criadas</th>
                                <th class="pe-3">Erros</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-3">{{ (int) ($summary['processed'] ?? 0) }}</td>
                                <td>{{ (int) ($summary['created'] ?? 0) }}</td>
                                <td>{{ (int) ($summary['updated'] ?? 0) }}</td>
                                <td>{{ (int) ($summary['families_created'] ?? 0) }}</td>
                                <td>{{ (int) ($summary['brands_created'] ?? 0) }}</td>
                                <td class="pe-3">{{ count($summary['errors'] ?? []) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (! empty($summary['errors']))
            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Erros por linha</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3">
                        @foreach ($summary['errors'] as $errorLine)
                            <li>{{ $errorLine }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    @endif
@endsection
