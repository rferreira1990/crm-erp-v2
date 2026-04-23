@php
    $log = $log ?? null;
    $isEdit = isset($log);
    $selectedType = old('type', $log->type ?? \App\Models\ConstructionSiteLog::TYPE_NOTE);
    $selectedAssignedUserId = old('assigned_user_id', $log->assigned_user_id ?? '');
@endphp

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Dados do Registo</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-3">
                <label for="log_date" class="form-label">Data</label>
                <input type="date" id="log_date" name="log_date" class="form-control @error('log_date') is-invalid @enderror" value="{{ old('log_date', optional($log->log_date ?? now())->format('Y-m-d')) }}" required>
                @error('log_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="type" class="form-label">Tipo</label>
                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                    @foreach (($typeOptions ?? []) as $value => $label)
                        <option value="{{ $value }}" @selected((string) $selectedType === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="assigned_user_id" class="form-label">Utilizador associado</label>
                <select id="assigned_user_id" name="assigned_user_id" class="form-select @error('assigned_user_id') is-invalid @enderror">
                    <option value="">Sem utilizador associado</option>
                    @foreach (($assignedUserOptions ?? []) as $assignedUser)
                        <option value="{{ $assignedUser->id }}" @selected((string) $selectedAssignedUserId === (string) $assignedUser->id)>{{ $assignedUser->name }}</option>
                    @endforeach
                </select>
                @error('assigned_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input type="hidden" name="is_important" value="0">
                    <input class="form-check-input" type="checkbox" id="is_important" name="is_important" value="1" @checked(old('is_important', $log->is_important ?? false))>
                    <label class="form-check-label" for="is_important">Importante</label>
                </div>
            </div>
            <div class="col-12">
                <label for="title" class="form-label">Titulo</label>
                <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $log->title ?? '') }}" maxlength="190" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Descricao</h5>
    </div>
    <div class="card-body">
        <label for="description" class="form-label">Descricao detalhada</label>
        <textarea id="description" name="description" rows="6" class="form-control @error('description') is-invalid @enderror" maxlength="10000" required>{{ old('description', $log->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Uploads</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="images" class="form-label">Imagens</label>
                <input type="file" id="images" name="images[]" class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp" multiple>
                @error('images')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('images.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="documents" class="form-label">Ficheiros</label>
                <input type="file" id="documents" name="documents[]" class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp" multiple>
                @error('documents')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('documents.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

@if ($isEdit)
    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Imagens existentes</h5>
        </div>
        <div class="card-body">
            @forelse ($log->images as $image)
                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                    <div>
                        <a href="{{ route('admin.construction-sites.logs.images.show', [$site->id, $log->id, $image->id]) }}" target="_blank" rel="noopener noreferrer">{{ $image->original_name }}</a>
                        @if ($image->is_primary)
                            <span class="badge badge-phoenix badge-phoenix-success ms-2">Primaria</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.construction-sites.logs.images.destroy', [$site->id, $log->id, $image->id]) }}" data-confirm="Tem a certeza que pretende remover esta imagem?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-phoenix-danger btn-sm">Remover</button>
                    </form>
                </div>
            @empty
                <div class="text-body-tertiary">Sem imagens registadas.</div>
            @endforelse
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Ficheiros existentes</h5>
        </div>
        <div class="card-body">
            @forelse ($log->files as $file)
                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                    <a href="{{ route('admin.construction-sites.logs.files.download', [$site->id, $log->id, $file->id]) }}" class="fw-semibold">{{ $file->original_name }}</a>
                    <form method="POST" action="{{ route('admin.construction-sites.logs.files.destroy', [$site->id, $log->id, $file->id]) }}" data-confirm="Tem a certeza que pretende remover este ficheiro?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-phoenix-danger btn-sm">Remover</button>
                    </form>
                </div>
            @empty
                <div class="text-body-tertiary">Sem ficheiros registados.</div>
            @endforelse
        </div>
    </div>
@endif

<div class="d-flex gap-2 justify-content-end">
    @if ($isEdit)
        <form method="POST" action="{{ route('admin.construction-sites.logs.destroy', [$site->id, $log->id]) }}" data-confirm="Tem a certeza que pretende eliminar este registo?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-phoenix-danger">Eliminar registo</button>
        </form>
    @endif
    <a href="{{ $isEdit ? route('admin.construction-sites.logs.show', [$site->id, $log->id]) : route('admin.construction-sites.logs.index', $site->id) }}" class="btn btn-phoenix-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Guardar alteracoes' : 'Criar registo' }}</button>
</div>
