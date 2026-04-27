@php
    $firstItem = $messages->firstItem();
    $lastItem = $messages->lastItem();
    $totalItems = $messages->total();
@endphp

<div class="px-lg-1" id="email-inbox-live-table">
    <div class="d-flex align-items-center flex-wrap position-sticky pb-2 bg-body z-2 email-toolbar inbox-toolbar">
        <div class="d-flex align-items-center flex-1 me-2">
            <button class="btn btn-sm p-0 me-2" type="button" onclick="location.reload()">
                <span class="text-primary fas fa-redo fs-10"></span>
            </button>
            <p class="fw-semibold fs-10 text-body-tertiary text-opacity-85 mb-0 lh-sm text-nowrap">
                Ultima atualizacao {{ now()->format('H:i') }}
            </p>
        </div>
        <div class="d-flex">
            <p class="text-body-tertiary text-opacity-85 fs-9 fw-semibold mb-0 me-3">
                A mostrar:
                <span class="text-body">{{ $firstItem ?? 0 }}-{{ $lastItem ?? 0 }}</span>
                de <span class="text-body">{{ $totalItems }}</span>
            </p>
        </div>
    </div>

    <div class="border-top border-translucent py-2 d-flex justify-content-between">
        <div class="form-check mb-0 fs-8">
            <input class="form-check-input" type="checkbox" disabled>
        </div>
        <div>
            <button class="btn p-0 me-2 text-body-quaternary text-body-tertiary text-opacity-85" type="button" disabled>
                <span class="fas fa-archive fs-10"></span>
            </button>
            <button class="btn p-0 me-2 text-body-quaternary text-body-tertiary text-opacity-85" type="button" disabled>
                <span class="fas fa-trash fs-10"></span>
            </button>
            <button class="btn p-0 me-2 text-body-quaternary text-body-tertiary text-opacity-85" type="button" disabled>
                <span class="fas fa-star fs-10"></span>
            </button>
            <button class="btn p-0 text-body-quaternary text-body-tertiary text-opacity-85" type="button" disabled>
                <span class="fas fa-tag fs-10"></span>
            </button>
        </div>
    </div>

    @forelse ($messages as $message)
        @php
            $sender = $message->senderLabel();
            $initial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($sender, 0, 1));
            $received = $message->received_at?->format('d/m H:i') ?? '-';
        @endphp
        <div class="border-top border-translucent hover-actions-trigger py-3">
            <div class="row align-items-sm-center gx-2">
                <div class="col-auto">
                    <div class="d-flex flex-column flex-sm-row">
                        <input class="form-check-input mb-2 m-sm-0 me-sm-2" type="checkbox" disabled>
                        <button class="btn p-0" type="button" disabled>
                            <span class="{{ $message->is_seen ? 'far text-body-quaternary' : 'fas text-warning' }} fa-star"></span>
                        </button>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="avatar avatar-s">
                        <div class="avatar-name rounded-circle"><span>{{ $initial !== '' ? $initial : '?' }}</span></div>
                    </div>
                </div>
                <div class="col-auto">
                    <a class="text-body-emphasis {{ $message->is_seen ? 'fw-semibold' : 'fw-bold' }} inbox-link fs-9"
                       href="{{ route('admin.email-messages.show', $message->id) }}">
                        {{ $sender }}
                    </a>
                </div>
                <div class="col-auto ms-auto">
                    <div class="hover-actions end-0">
                        <a href="{{ route('admin.email-messages.show', $message->id) }}"
                           class="btn btn-phoenix-secondary btn-icon"
                           title="Abrir">
                            <span class="fa-solid fa-eye"></span>
                        </a>
                    </div>
                    <span class="fs-10 {{ $message->is_seen ? '' : 'fw-bold' }}">{{ $received }}</span>
                </div>
            </div>

            <div class="ms-4 mt-n3 mt-sm-0 ms-sm-11">
                <a class="d-block inbox-link" href="{{ route('admin.email-messages.show', $message->id) }}">
                    <span class="fs-9 line-clamp-1 {{ $message->is_seen ? 'text-body-highlight' : 'text-body-emphasis' }}">
                        {{ $message->subjectLabel() }}
                    </span>
                    @if ($message->snippetLabel() !== '')
                        <p class="fs-9 ps-0 text-body-tertiary mb-0 line-clamp-2">{{ $message->snippetLabel() }}</p>
                    @endif
                </a>

                <div class="mt-2">
                    @if ($message->has_attachments)
                        <span class="d-inline-flex align-items-center border border-translucent rounded-pill px-3 py-1 me-2 mt-1 inbox-link">
                            <span class="fas fa-paperclip text-warning fs-9"></span>
                            <span class="ms-2 fw-bold fs-10 text-body">Com anexos</span>
                        </span>
                    @endif
                    @if (! $message->is_seen)
                        <span class="d-inline-flex align-items-center border border-translucent rounded-pill px-3 py-1 me-2 mt-1 inbox-link">
                            <span class="fas fa-circle text-primary fs-11"></span>
                            <span class="ms-2 fw-bold fs-10 text-body">Nao lido</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="border-top border-translucent py-4 text-center text-body-tertiary">
            Sem emails sincronizados.
        </div>
    @endforelse

    @if ($messages->hasPages())
        <div class="pt-3">
            {{ $messages->links() }}
        </div>
    @endif
</div>
