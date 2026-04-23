@extends('layouts.admin')

@section('title', 'Novo movimento manual')
@section('page_title', 'Novo movimento manual')
@section('page_subtitle', 'Saidas, ajustes negativos e ajustes positivos')

@section('page_actions')
    <a href="{{ route('admin.stock-movements.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.stock-movements.index') }}">Movimentos de stock</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo movimento manual</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Registar movimento manual</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.stock-movements.store') }}" class="row g-3" id="manualStockMovementForm">
                @csrf
                <div class="col-12 col-md-6">
                    <label for="article_id" class="form-label">Artigo <span class="text-danger">*</span></label>
                    <select id="article_id" name="article_id" class="form-select @error('article_id') is-invalid @enderror" required>
                        <option value="">Selecionar artigo</option>
                        @foreach ($articleOptions as $article)
                            <option value="{{ $article->id }}" data-stock="{{ number_format((float) $article->stock_quantity, 3, '.', '') }}" @selected((int) old('article_id') === (int) $article->id)>
                                {{ $article->code }} - {{ $article->designation }} (stock {{ number_format((float) $article->stock_quantity, 3, ',', '.') }})
                            </option>
                        @endforeach
                    </select>
                    @error('article_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-3">
                    <label for="type" class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                        @foreach ($manualTypeOptions as $manualType)
                            <option value="{{ $manualType }}" @selected(old('type', $defaultType) === $manualType)>
                                {{ $typeLabels[$manualType] ?? $manualType }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-3">
                    <label for="movement_date" class="form-label">Data movimento</label>
                    <input type="date" id="movement_date" name="movement_date" value="{{ old('movement_date', $defaultMovementDate) }}" class="form-control @error('movement_date') is-invalid @enderror">
                    @error('movement_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-3">
                    <label for="quantity" class="form-label">Quantidade <span class="text-danger">*</span></label>
                    <input type="number" step="0.001" min="0.001" id="quantity" name="quantity" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required>
                    @error('quantity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-5">
                    <label for="reason_code" class="form-label">Motivo <span class="text-danger">*</span></label>
                    <select id="reason_code" name="reason_code" class="form-select @error('reason_code') is-invalid @enderror" required>
                        <option value="">Selecionar motivo</option>
                        @foreach ($reasonOptionsByType as $movementType => $reasonCodes)
                            @foreach ($reasonCodes as $reasonCode)
                                <option value="{{ $reasonCode }}" data-type="{{ $movementType }}" @selected(old('reason_code') === $reasonCode)>
                                    {{ $reasonLabels[$reasonCode] ?? $reasonCode }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('reason_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Stock atual do artigo</label>
                    <input type="text" class="form-control" id="articleStockDisplay" value="-" readonly>
                </div>

                <div class="col-12">
                    <label for="notes" class="form-label">Notas</label>
                    <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.stock-movements.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Confirmar movimento</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const typeSelect = document.getElementById('type');
            const reasonSelect = document.getElementById('reason_code');
            const articleSelect = document.getElementById('article_id');
            const articleStockDisplay = document.getElementById('articleStockDisplay');

            if (!typeSelect || !reasonSelect || !articleSelect || !articleStockDisplay) {
                return;
            }

            const updateReasonOptions = () => {
                const selectedType = typeSelect.value;
                let hasVisibleSelected = false;

                Array.from(reasonSelect.options).forEach((option, index) => {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }

                    const allowedType = option.getAttribute('data-type');
                    const shouldShow = allowedType === selectedType;
                    option.hidden = !shouldShow;
                    option.disabled = !shouldShow;

                    if (shouldShow && option.selected) {
                        hasVisibleSelected = true;
                    }
                });

                if (!hasVisibleSelected) {
                    reasonSelect.value = '';
                }
            };

            const updateArticleStock = () => {
                const selectedOption = articleSelect.options[articleSelect.selectedIndex];
                const stockValue = selectedOption ? selectedOption.getAttribute('data-stock') : null;
                articleStockDisplay.value = stockValue !== null ? stockValue.replace('.', ',') : '-';
            };

            typeSelect.addEventListener('change', updateReasonOptions);
            articleSelect.addEventListener('change', updateArticleStock);

            updateReasonOptions();
            updateArticleStock();
        })();
    </script>
@endpush
