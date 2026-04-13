@extends('layouts.admin')

@section('title', 'Ficha do cliente')
@section('page_title', 'Ficha do cliente')
@section('page_subtitle', 'Detalhe completo do cliente')

@section('page_actions')
    <a href="{{ route('admin.customers.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    <a href="{{ route('admin.customers.edit', $customer->id) }}" class="btn btn-primary btn-sm">Editar cliente</a>
    @if ($customer->customer_type === \App\Models\Customer::TYPE_COMPANY)
        <a href="{{ route('admin.customers.contacts.create', $customer->id) }}" class="btn btn-phoenix-secondary btn-sm">Adicionar contacto</a>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Clientes</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $customer->name }}</li>
    </ol>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="avatar avatar-4xl">
                            @if ($customer->logo_path)
                                <img class="rounded-circle" src="{{ route('admin.customers.logo.show', $customer->id) }}" alt="{{ $customer->name }}">
                            @else
                                <div class="avatar-name rounded-circle">
                                    <span>{{ mb_substr($customer->name, 0, 1) }}</span>
                                </div>
                            @endif
                        </div>
                        <div>
                            <h3 class="mb-1">{{ $customer->name }}</h3>
                            <p class="mb-1 text-body-secondary">{{ $customerTypeLabels[$customer->customer_type] ?? $customer->customer_type }}</p>
                            @if ($customer->is_active)
                                <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                            @else
                                <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                            @endif
                        </div>
                    </div>

                    <div class="border-top border-dashed pt-3">
                        <div class="mb-2"><span class="text-body-tertiary">NIF:</span> <span class="fw-semibold">{{ $customer->nif ?: '-' }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Email:</span> <span class="fw-semibold">{{ $customer->email ?: '-' }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Telefone:</span> <span class="fw-semibold">{{ $customer->phone ?: '-' }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Telemovel:</span> <span class="fw-semibold">{{ $customer->mobile ?: '-' }}</span></div>
                        <div><span class="text-body-tertiary">Localidade:</span> <span class="fw-semibold">{{ trim(($customer->locality ?? '').' / '.($customer->city ?? ''), ' /') ?: '-' }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-8">
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Morada</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">{{ $customer->address ?: '-' }}</p>
                            <p class="mb-2">{{ $customer->postal_code ?: '-' }}</p>
                            <p class="mb-2">{{ $customer->locality ?: '-' }}{{ $customer->city ? ' / '.$customer->city : '' }}</p>
                            <p class="mb-0">{{ $customer->country?->name ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Condicoes financeiras</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2"><span class="text-body-tertiary">Escalao de preco:</span> <span class="fw-semibold">{{ $customer->priceTier?->name ?: 'Normal / default' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Condicao pagamento:</span> <span class="fw-semibold">{{ $customer->paymentTerm?->name ?: '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">IVA habitual:</span> <span class="fw-semibold">{{ $customer->defaultVatRate ? $customer->defaultVatRate->name.' ('.number_format((float) $customer->defaultVatRate->rate, 2).'%)' : '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Desconto habitual:</span> <span class="fw-semibold">{{ $customer->default_commercial_discount !== null ? number_format((float) $customer->default_commercial_discount, 2).'%' : '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Limite credito:</span> <span class="fw-semibold">{{ $customer->credit_limit !== null ? number_format((float) $customer->credit_limit, 2) : '-' }}</span></div>
                            <div><span class="text-body-tertiary">Estado limite:</span> <span class="fw-semibold">{{ $customer->has_credit_limit ? 'Ativo' : 'Desativado' }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Notas</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0 text-body-secondary">{{ $customer->notes ?: 'Sem notas.' }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Comentarios para impressao</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0 text-body-secondary">{{ $customer->print_comments ?: 'Sem comentarios.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Contactos do cliente</h5>
            @if ($customer->customer_type === \App\Models\Customer::TYPE_COMPANY)
                <a href="{{ route('admin.customers.contacts.create', $customer->id) }}" class="btn btn-primary btn-sm">Adicionar contacto</a>
            @endif
        </div>
        <div class="card-body p-0">
            @if ($customer->customer_type !== \App\Models\Customer::TYPE_COMPANY)
                <div class="p-3 text-body-tertiary">Contactos apenas disponiveis para clientes do tipo empresa.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                        <thead class="bg-body-tertiary">
                            <tr>
                                <th class="ps-3">Nome</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Cargo</th>
                                <th>Preferencial</th>
                                <th>Observacoes</th>
                                <th class="text-end pe-3">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($customer->contacts as $contact)
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $contact->name }}</td>
                                    <td>{{ $contact->email ?: '-' }}</td>
                                    <td>{{ $contact->phone ?: '-' }}</td>
                                    <td>{{ $contact->job_title ?: '-' }}</td>
                                    <td>
                                        @if ($contact->is_primary)
                                            <span class="badge badge-phoenix badge-phoenix-success">Sim</span>
                                        @else
                                            <span class="badge badge-phoenix badge-phoenix-secondary">Nao</span>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit((string) ($contact->notes ?? ''), 60, '...') ?: '-' }}</td>
                                    <td class="text-end pe-3">
                                        <a href="{{ route('admin.customers.contacts.edit', [$customer->id, $contact->id]) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-body-tertiary">Sem contactos registados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
