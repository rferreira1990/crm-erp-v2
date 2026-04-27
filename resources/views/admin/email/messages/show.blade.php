@extends('layouts.admin')

@section('title', 'Email')
@section('page_title', 'Email recebido')
@section('page_subtitle', 'Detalhe da mensagem da Inbox')

@section('page_actions')
    <a href="{{ route('admin.email-inbox.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar a Inbox</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.email-inbox.index') }}">Caixa de Email</a></li>
        <li class="breadcrumb-item active" aria-current="page">Mensagem</li>
    </ol>
@endsection

@section('content')
    @php
        $senderLabel = $message->senderLabel();
        $senderInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($senderLabel, 0, 1));
        $subject = $message->subjectLabel();
        $isSeen = (bool) $message->is_seen;
        $hasAttachments = (bool) $message->has_attachments;
        $attachmentCount = $message->attachments->count();
        $toLabel = trim(implode(' ', array_filter([(string) ($message->to_name ?? ''), (string) ($message->to_email ?? '')])));
        $toLabel = $toLabel !== '' ? $toLabel : '-';

        $bodyText = is_string($message->body_text) ? trim($message->body_text) : '';
        $htmlFallbackText = '';
        if ($bodyText === '' && is_string($message->body_html) && trim($message->body_html) !== '') {
            $safeHtml = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $message->body_html) ?? '';
            $htmlFallbackText = trim(strip_tags($safeHtml));
        }
        $displayBody = $bodyText !== '' ? $bodyText : $htmlFallbackText;

        $formatBytes = static function (?int $bytes): string {
            if ($bytes === null || $bytes < 0) {
                return '-';
            }

            $units = ['B', 'KB', 'MB', 'GB'];
            $size = (float) $bytes;
            $unit = 0;

            while ($size >= 1024 && $unit < count($units) - 1) {
                $size /= 1024;
                $unit++;
            }

            return number_format($size, $unit === 0 ? 0 : 2, ',', '.').' '.$units[$unit];
        };
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="email-container">
        <div class="row gx-lg-6 gx-3 py-3 z-2 position-sticky bg-body email-header rounded-2 mb-3">
            <div class="col-auto">
                <a class="btn btn-phoenix-secondary px-3" href="{{ route('admin.email-inbox.index') }}">
                    <span class="fa-solid fa-angle-left me-1"></span>Inbox
                </a>
            </div>
            <div class="col-auto flex-1">
                <div class="search-box w-100">
                    <form class="position-relative" action="{{ route('admin.email-inbox.index') }}" method="GET">
                        <input class="form-control search-input search" type="search" name="q" placeholder="Pesquisar na Inbox..." aria-label="Pesquisar">
                        <span class="fas fa-search search-box-icon"></span>
                    </form>
                </div>
            </div>
            <div class="col-auto">
                <form method="POST" action="{{ route('admin.email-inbox.sync') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-phoenix-primary px-3">Sincronizar</button>
                </form>
            </div>
        </div>

        <div class="row g-lg-6 mb-4">
            <div class="col-lg-auto">
                <div class="email-sidebar email-sidebar-width bg-body border rounded-2">
                    <div class="email-content scrollbar-overlay p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <p class="text-uppercase fs-10 text-body-tertiary text-opacity-85 mb-2 fw-bold">Mailbox</p>
                        </div>

                        <ul class="nav flex-column border-top border-translucent fs-9 vertical-nav mb-4">
                            <li class="nav-item">
                                <a class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start outline-none active"
                                   href="{{ route('admin.email-inbox.index') }}">
                                    <div class="d-flex align-items-center">
                                        <span class="me-2 nav-icons fas fa-inbox"></span>
                                        <span class="flex-1">Inbox</span>
                                    </div>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start outline-none disabled"
                                   href="#!" tabindex="-1" aria-disabled="true">
                                    <div class="d-flex align-items-center">
                                        <span class="me-2 nav-icons fas fa-paper-plane"></span>
                                        <span class="flex-1">Enviados</span>
                                    </div>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start outline-none disabled"
                                   href="#!" tabindex="-1" aria-disabled="true">
                                    <div class="d-flex align-items-center">
                                        <span class="me-2 nav-icons fas fa-file-alt"></span>
                                        <span class="flex-1">Rascunhos</span>
                                    </div>
                                </a>
                            </li>
                        </ul>

                        <p class="text-uppercase fs-10 text-body-tertiary text-opacity-85 mb-2 fw-bold">Etiquetas</p>
                        <ul class="nav flex-column border-top border-translucent fs-9 vertical-nav mb-0">
                            <li class="nav-item">
                                <a class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start outline-none disabled"
                                   href="#!" tabindex="-1" aria-disabled="true">
                                    <div class="d-flex align-items-center">
                                        <span class="ms-n1 me-2 fa-solid fa-circle text-primary" style="font-size:8px;"></span>
                                        <span class="flex-1">Clientes</span>
                                    </div>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-2 ps-0 pe-3 border-end border-bottom border-translucent text-start outline-none disabled"
                                   href="#!" tabindex="-1" aria-disabled="true">
                                    <div class="d-flex align-items-center">
                                        <span class="ms-n1 me-2 fa-solid fa-circle text-warning" style="font-size:8px;"></span>
                                        <span class="flex-1">Comercial</span>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card email-content">
                    <div class="card-body overflow-hidden">
                        <div class="d-flex flex-between-center pb-3 border-bottom border-translucent mb-4">
                            <a class="btn btn-link p-0 text-body-secondary me-3" href="{{ route('admin.email-inbox.index') }}">
                                <span class="fa-solid fa-angle-left fw-bolder fs-8"></span>
                            </a>
                            <h3 class="flex-1 mb-0 lh-sm line-clamp-1">{{ $subject }}</h3>
                            <div class="btn-reveal-trigger">
                                <button class="btn btn-sm dropdown-toggle dropdown-caret-none transition-none d-flex btn-reveal" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="fas fa-ellipsis-h"></span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end py-2">
                                    <a class="dropdown-item disabled" href="#!" tabindex="-1" aria-disabled="true">Responder</a>
                                    <a class="dropdown-item disabled" href="#!" tabindex="-1" aria-disabled="true">Reencaminhar</a>
                                    <a class="dropdown-item disabled" href="#!" tabindex="-1" aria-disabled="true">Marcar como lido</a>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-hidden scrollbar email-detail-content">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                @if ($isSeen)
                                    <span class="badge badge-phoenix badge-phoenix-success">Lido</span>
                                @else
                                    <span class="badge badge-phoenix badge-phoenix-warning">Nao lido</span>
                                @endif
                                @if ($hasAttachments)
                                    <span class="badge badge-phoenix badge-phoenix-info">Com anexos</span>
                                @else
                                    <span class="badge badge-phoenix badge-phoenix-secondary">Sem anexos</span>
                                @endif
                                <span class="text-body-tertiary fs-9">
                                    {{ $message->received_at?->format('d/m/Y H:i') ?? '-' }}
                                    <span class="mx-1">&bull;</span>{{ $message->account?->name ?? '-' }}
                                </span>
                            </div>

                            <div class="row align-items-center gy-3 gx-0 mb-8">
                                <div class="col-12 col-sm-auto d-flex order-sm-1">
                                    <button class="btn p-0 me-4" type="button" disabled data-bs-toggle="tooltip" data-bs-title="Reply"><span class="fa-solid fa-reply text-body-quaternary"></span></button>
                                    <button class="btn p-0 me-4" type="button" disabled data-bs-toggle="tooltip" data-bs-title="Archive"><span class="fa-solid fa-archive text-body-quaternary"></span></button>
                                    <button class="btn p-0 me-4" type="button" disabled data-bs-toggle="tooltip" data-bs-title="Forward"><span class="fa-solid fa-share text-body-quaternary"></span></button>
                                </div>
                                <div class="col-auto">
                                    <div class="avatar avatar-l">
                                        <div class="avatar-name rounded-circle"><span>{{ $senderInitial !== '' ? $senderInitial : '?' }}</span></div>
                                    </div>
                                </div>
                                <div class="col-auto flex-1">
                                    <div class="d-flex mb-1 flex-wrap">
                                        <h5 class="mb-0 text-body-highlight me-2">{{ $senderLabel }}</h5>
                                        <p class="mb-0 lh-sm text-body-tertiary fs-9 text-nowrap">&lt; {{ $message->from_email ?: '-' }} &gt;</p>
                                    </div>
                                    <p class="mb-0 fs-9">
                                        <span class="text-body-tertiary">para </span>
                                        <span class="fw-bold text-body-secondary">{{ $toLabel }}</span>
                                        @if ($ccHeader)
                                            <span class="text-body-tertiary ms-2">cc </span>
                                            <span class="text-body-secondary">{{ $ccHeader }}</span>
                                        @endif
                                    </p>
                                    <p class="mb-0 fs-10 text-body-tertiary mt-1">
                                        UID {{ $message->message_uid }} <span class="mx-1">&bull;</span> Pasta {{ $message->folder ?: 'INBOX' }}
                                    </p>
                                </div>
                            </div>

                            <div class="text-body-highlight fs-9 w-100 mb-8">
                                @if ($displayBody !== '')
                                    {!! nl2br(e($displayBody)) !!}
                                @else
                                    <p class="mb-0 text-body-tertiary">Sem conteudo de mensagem disponivel.</p>
                                @endif
                            </div>

                            <div class="d-flex align-items-center mb-4">
                                <button class="btn btn-link text-body-highlight fs-8 text-decoration-none p-0" type="button">
                                    <span class="fa-solid fa-paperclip me-2"></span>{{ $attachmentCount }} Anexos
                                </button>
                            </div>

                            <div class="row pb-8 border-bottom mb-4 gx-0 gy-2 border-translucent">
                                @forelse ($message->attachments as $attachment)
                                    @php
                                        $ext = strtolower(pathinfo((string) $attachment->filename, PATHINFO_EXTENSION));
                                        $isImage = str_starts_with(strtolower((string) ($attachment->mime_type ?? '')), 'image/');
                                        $icon = match (true) {
                                            $isImage => 'fa-file-image',
                                            $ext === 'pdf' => 'fa-file-pdf',
                                            in_array($ext, ['zip', 'rar', '7z'], true) => 'fa-file-zipper',
                                            in_array($ext, ['xls', 'xlsx', 'csv'], true) => 'fa-file-excel',
                                            in_array($ext, ['doc', 'docx'], true) => 'fa-file-word',
                                            default => 'fa-file',
                                        };
                                    @endphp
                                    <div class="col-auto me-3">
                                        @if ($attachment->storage_path)
                                            <a class="text-decoration-none d-flex align-items-center"
                                               href="{{ route('admin.email-attachments.download', [$message->id, $attachment->id]) }}">
                                        @else
                                            <div class="text-decoration-none d-flex align-items-center">
                                        @endif
                                                <div class="btn-icon btn-icon-xl border rounded-3 text-body-quaternary text-opacity-75 flex-column me-2">
                                                    <span class="fa-solid {{ $icon }} fs-8 mb-1"></span>
                                                    <p class="mb-0 fs-10 fw-bold">{{ strtoupper($ext !== '' ? $ext : 'FILE') }}</p>
                                                </div>
                                                <div>
                                                    <h6 class="text-body-highlight mb-1">{{ $attachment->filename }}</h6>
                                                    <p class="fs-9 mb-0 text-body-tertiary lh-1">{{ $formatBytes($attachment->size_bytes) }}</p>
                                                </div>
                                        @if ($attachment->storage_path)
                                            </a>
                                        @else
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="col-12">
                                        <p class="text-body-tertiary mb-0">Sem anexos.</p>
                                    </div>
                                @endforelse
                            </div>

                            <div class="d-flex justify-content-between">
                                <button class="btn btn-phoenix-secondary me-1 text-nowrap px-2 px-sm-4" disabled>
                                    Reply<span class="fa-solid fa-reply ms-2 fs-10"></span>
                                </button>
                                <button class="btn btn-phoenix-secondary me-1 text-nowrap px-2 px-sm-4" disabled>
                                    Reply All<span class="fa-solid fa-reply-all ms-2 fs-10"></span>
                                </button>
                                <button class="btn btn-phoenix-secondary ms-auto text-nowrap px-2 px-sm-4" disabled>
                                    Forward<span class="fa-solid fa-share ms-2 fs-10"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

