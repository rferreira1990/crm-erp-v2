@php
    $contact = $contact ?? null;
    $isEdit = isset($contact);
@endphp

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">{{ $isEdit ? 'Editar contacto' : 'Novo contacto' }}</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="name" class="form-label">Nome</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $contact->name ?? '') }}"
                    class="form-control @error('name') is-invalid @enderror"
                    maxlength="190"
                    required
                >
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="job_title" class="form-label">Cargo</label>
                <input
                    type="text"
                    id="job_title"
                    name="job_title"
                    value="{{ old('job_title', $contact->job_title ?? '') }}"
                    class="form-control @error('job_title') is-invalid @enderror"
                    maxlength="190"
                >
                @error('job_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $contact->email ?? '') }}"
                    class="form-control @error('email') is-invalid @enderror"
                    maxlength="190"
                >
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="phone" class="form-label">Telefone</label>
                <input
                    type="text"
                    id="phone"
                    name="phone"
                    value="{{ old('phone', $contact->phone ?? '') }}"
                    class="form-control @error('phone') is-invalid @enderror"
                    maxlength="30"
                >
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="notes" class="form-label">Notas</label>
                <textarea id="notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" maxlength="5000">{{ old('notes', $contact->notes ?? '') }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="form-check mt-2">
                    <input type="hidden" name="is_primary" value="0">
                    <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1" @checked(old('is_primary', $contact->is_primary ?? false))>
                    <label class="form-check-label" for="is_primary">Contacto preferencial</label>
                </div>
                @error('is_primary')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 justify-content-end">
    <a href="{{ route('admin.suppliers.edit', $supplier->id) }}" class="btn btn-phoenix-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Guardar alteracoes' : 'Criar contacto' }}</button>
</div>
