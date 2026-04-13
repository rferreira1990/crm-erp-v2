<div class="card mt-4">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Contactos do cliente</h5>
        @if ($customer->customer_type === \App\Models\Customer::TYPE_COMPANY)
            <a href="{{ route('admin.customers.contacts.create', $customer->id) }}" class="btn btn-primary btn-sm">
                Novo contacto
            </a>
        @endif
    </div>
    <div class="card-body p-0">
        @if ($customer->customer_type !== \App\Models\Customer::TYPE_COMPANY)
            <div class="p-3 text-body-tertiary">
                Contactos apenas disponiveis para clientes do tipo empresa.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Nome</th>
                            <th>Cargo</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Preferencial</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->contacts as $contact)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $contact->name }}</td>
                                <td>{{ $contact->job_title ?? '-' }}</td>
                                <td>{{ $contact->email ?? '-' }}</td>
                                <td>{{ $contact->phone ?? '-' }}</td>
                                <td>
                                    @if ($contact->is_primary)
                                        <span class="badge badge-phoenix badge-phoenix-success">Sim</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Nao</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.customers.contacts.edit', [$customer->id, $contact->id]) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Editar
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.customers.contacts.destroy', [$customer->id, $contact->id]) }}"
                                            data-confirm="Tem a certeza que pretende remover este contacto?"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-phoenix-danger btn-sm">Remover</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-body-tertiary">Sem contactos registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
