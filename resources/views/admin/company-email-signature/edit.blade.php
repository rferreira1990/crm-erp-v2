@extends('layouts.admin')

@section('title', 'Assinatura de Email')
@section('page_title', 'Assinatura de Email')
@section('page_subtitle', 'Assinatura usada nos emails enviados pelo ERP')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Assinatura de email</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.company-email-signature.update') }}" class="mb-4">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">HTML da assinatura</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="mail_signature_html" class="form-label">Assinatura (aceita HTML, incluindo imagem)</label>
                    <textarea
                        id="mail_signature_html"
                        data-signature-editor
                        name="mail_signature_html"
                        rows="12"
                        class="form-control @error('mail_signature_html') is-invalid @enderror"
                        placeholder="<p>Cumprimentos,<br>Nome</p><img src=&quot;https://...&quot; alt=&quot;logo&quot;>"
                    >{{ old('mail_signature_html', $company->mail_signature_html) }}</textarea>
                    <div class="form-text">Use o editor para negrito, listas, links e imagem. A assinatura e guardada em HTML.</div>
                    @error('mail_signature_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Guardar assinatura</button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/phoenix/vendors/tinymce/tinymce.min.js') }}"></script>
    <script>
        (() => {
            const target = document.querySelector('textarea[data-signature-editor]');
            if (!target || !window.tinymce) {
                return;
            }

            tinymce.init({
                target,
                height: 420,
                menubar: false,
                branding: false,
                convert_urls: false,
                relative_urls: false,
                remove_script_host: false,
                plugins: 'lists link image table',
                toolbar: 'undo redo | blocks | bold italic underline | forecolor backcolor | bullist numlist | link image table | removeformat',
                block_formats: 'Paragrafo=p;Titulo 1=h1;Titulo 2=h2;Titulo 3=h3',
                content_style: 'body{font-family:Nunito Sans, Arial, sans-serif;font-size:14px;line-height:1.6;} img{max-width:100%;height:auto;}',
            });
        })();
    </script>
@endpush
