@extends('layouts.admin')

@section('title', 'Novo Documento de Venda')
@section('page_title', 'Novo Documento de Venda')
@section('page_subtitle', 'Criacao manual, por orcamento ou por obra')

@section('page_actions')
    <a href="{{ route('admin.sales-documents.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.sales-documents.index') }}">Documentos de Venda</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo documento</li>
    </ol>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Origem do documento</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.sales-documents.create') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label for="source" class="form-label">Origem</label>
                    <select id="source" name="source" class="form-select">
                        @foreach ($sourceLabels as $value => $label)
                            <option value="{{ $value }}" @selected($sourceType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-4">
                    <label for="quote_id" class="form-label">Orcamento (quando aplicavel)</label>
                    <select id="quote_id" name="quote_id" class="form-select">
                        <option value="">Selecionar</option>
                        @foreach ($quotes as $quote)
                            <option value="{{ $quote->id }}" @selected((string) $selectedQuoteId === (string) $quote->id)>
                                {{ $quote->number }} - {{ $quote->customer?->name ?? '-' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-4">
                    <label for="construction_site_id" class="form-label">Obra (quando aplicavel)</label>
                    <select id="construction_site_id" name="construction_site_id" class="form-select">
                        <option value="">Selecionar</option>
                        @foreach ($constructionSites as $site)
                            <option value="{{ $site->id }}" @selected((string) $selectedConstructionSiteId === (string) $site->id)>
                                {{ $site->code }} - {{ $site->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <button type="submit" class="btn btn-phoenix-secondary w-100">Carregar origem</button>
                </div>
            </form>
        </div>
    </div>

    @include('admin.sales-documents._form', [
        'isEdit' => false,
        'formAction' => route('admin.sales-documents.store'),
    ])
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sourceSelect = document.getElementById('source');
            const quoteField = document.getElementById('quote_id');
            const siteField = document.getElementById('construction_site_id');

            const updateSourceFields = () => {
                if (!sourceSelect || !quoteField || !siteField) {
                    return;
                }

                const source = sourceSelect.value;
                quoteField.disabled = source !== '{{ \App\Models\SalesDocument::SOURCE_QUOTE }}';
                siteField.disabled = source !== '{{ \App\Models\SalesDocument::SOURCE_CONSTRUCTION_SITE }}';
            };

            if (sourceSelect) {
                sourceSelect.addEventListener('change', updateSourceFields);
                updateSourceFields();
            }
        });
    </script>
@endpush

