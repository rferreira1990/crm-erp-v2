<?php

namespace App\Services\Admin;

use App\Models\SalesDocumentReceipt;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SalesDocumentReceiptPdfService
{
    public function generateAndStore(SalesDocumentReceipt $receipt): string
    {
        $receipt->loadMissing([
            'company:id,name,nif,address,postal_code,locality,city,email,phone,mobile,logo_path',
            'salesDocument:id,number,issue_date,currency,grand_total',
            'customer:id,name,nif,email,phone,mobile,address,postal_code,locality,city',
            'paymentMethod:id,name',
            'creator:id,name',
            'canceller:id,name',
        ]);

        $companyLogoDataUri = $this->companyLogoDataUri($receipt->company?->logo_path);

        $html = view('admin.sales-document-receipts.pdf', [
            'receipt' => $receipt,
            'companyLogoDataUri' => $companyLogoDataUri,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $path = 'sales-document-receipts/'.$receipt->company_id.'/'.$receipt->id.'/pdf/'
            .Str::slug($receipt->number).'-'.now()->format('YmdHis').'.pdf';

        Storage::disk('local')->put($path, $pdf->output());

        if ($receipt->pdf_path && $receipt->pdf_path !== $path) {
            $this->delete($receipt->pdf_path);
        }

        $receipt->forceFill(['pdf_path' => $path])->save();

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
