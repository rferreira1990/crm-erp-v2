@extends('layouts.admin')

@section('title', 'Superadmin - Convites')
@section('page_title', 'Convites')
@section('page_subtitle', 'Convites para admins iniciais de empresa')

@section('page_actions')
    <a href="{{ route('superadmin.invitations.create') }}" class="btn btn-primary btn-sm">
        Novo convite
    </a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('superadmin.home') }}">Superadmin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Convites</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Convites enviados</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Email</th>
                            <th>Empresa</th>
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
                                <td>{{ $invitation->company?->name ?? '-' }}</td>
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
                                <td>{{ $invitation->expires_at->format('d/m/Y H:i') }}</td>
                                <td class="text-end pe-3">
                                    @if ($invitation->isPending())
                                        <form method="POST" action="{{ route('superadmin.invitations.destroy', $invitation) }}">
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
                                <td colspan="6" class="text-center py-4 text-body-tertiary">Sem convites registados.</td>
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
