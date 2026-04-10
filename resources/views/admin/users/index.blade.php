@extends('layouts.admin')

@section('title', 'Utilizadores')
@section('page_title', 'Utilizadores')
@section('page_subtitle', 'Gestao de utilizadores da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.user-invitations.create') }}" class="btn btn-primary btn-sm">
        Convidar utilizador
    </a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Utilizadores</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Utilizadores da empresa</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Nome</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $companyUser)
                            @php($isSelf = auth()->id() === $companyUser->id)
                            <tr>
                                <td class="ps-3">{{ $companyUser->name }}</td>
                                <td>{{ $companyUser->email }}</td>
                                <td class="text-nowrap">
                                    <form method="POST" action="{{ route('admin.users.update', $companyUser) }}" class="d-flex gap-2 align-items-center">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="form-select form-select-sm" @disabled($isSelf)>
                                            @foreach ($assignableRoles as $role)
                                                <option value="{{ $role->name }}" @selected($companyUser->hasRole($role->name))>
                                                    {{ $role->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-phoenix-secondary btn-sm" @disabled($isSelf)>
                                            Guardar
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    @if ($companyUser->is_active)
                                        <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-warning">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <form method="POST" action="{{ route('admin.users.toggle-active', $companyUser) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm {{ $companyUser->is_active ? 'btn-phoenix-danger' : 'btn-phoenix-success' }}" @disabled($isSelf)>
                                            {{ $companyUser->is_active ? 'Desativar' : 'Ativar' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-body-tertiary">Sem utilizadores registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            <div class="card-footer">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Convites de utilizadores</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Email</th>
                            <th>Role</th>
                            <th>Estado</th>
                            <th>Expira em</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invitations as $invitation)
                            @php($status = $invitation->status())
                            <tr>
                                <td class="ps-3">{{ $invitation->email }}</td>
                                <td><code>{{ $invitation->role }}</code></td>
                                <td>
                                    @if ($status === 'pending')
                                        <span class="badge badge-phoenix badge-phoenix-info">Pendente</span>
                                    @elseif ($status === 'accepted')
                                        <span class="badge badge-phoenix badge-phoenix-success">Aceite</span>
                                    @elseif ($status === 'expired')
                                        <span class="badge badge-phoenix badge-phoenix-warning">Expirado</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-danger">Cancelado</span>
                                    @endif
                                </td>
                                <td>{{ $invitation->expires_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="text-end pe-3">
                                    @if ($invitation->isPending())
                                        <form method="POST" action="{{ route('admin.user-invitations.destroy', $invitation) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-phoenix-danger btn-sm">Cancelar</button>
                                        </form>
                                    @else
                                        <span class="text-body-tertiary">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-body-tertiary">Sem convites registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($invitations->hasPages())
            <div class="card-footer">
                {{ $invitations->links() }}
            </div>
        @endif
    </div>
@endsection
