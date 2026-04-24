<?php

namespace App\Services\Admin;

use App\Models\SalesDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SalesDocumentPdfService
{
    public function generateAndStore(SalesDocument $document): string
    {
        $document->loadMissing([
            'company:id,name,nif,address,postal_code,locality,city,email,phone,mobile,logo_path',
            'customer:id,name',
            'quote:id,number',
            'constructionSite:id,code,name',
            'creator:id,name',
            'items' => fn ($query) => $query
                ->with([
                    'article:id,code,designation',
                    'unit:id,code,name',
                ])
                ->orderBy('line_order')
                ->orderBy('id'),
        ]);

        $companyLogoDataUri = $this->companyLogoDataUri($document->company?->logo_path);

        $html = view('admin.sales-documents.pdf', [
            'document' => $document,
            'companyLogoDataUri' => $companyLogoDataUri,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $path = 'sales-documents/'.$document->company_id.'/'.$document->id.'/pdf/'.Str::slug($document->number).'-'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        if ($document->pdf_path && $document->pdf_path !== $path) {
            $this->delete($document->pdf_path);
        }

        $document->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('local')->delete($path);
    }

    private function companyLogoDataUri(?string $logoPath): ?string
    {
        $path = trim((string) $logoPath);
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($path);
        if ($contents === '') {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path);
        if (! is_string($mime) || ! str_starts_with($mime, 'image/')) {
            $mime = 'image/png';
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}

