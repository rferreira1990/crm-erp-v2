<?php

namespace App\Services\Admin;

use App\Models\SupplierQuoteRequest;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupplierQuoteRequestPdfService
{
    public function generateAndStore(SupplierQuoteRequest $rfq): string
    {
        $rfq->loadMissing([
            'company:id,name,nif,address,postal_code,locality,city,email,phone,mobile,website,logo_path',
            'creator:id,name',
            'assignedUser:id,name',
            'items' => fn ($query) => $query
                ->orderBy('line_order')
                ->orderBy('id'),
        ]);

        $companyLogoDataUri = $this->companyLogoDataUri($rfq->company?->logo_path);

        $html = view('admin.rfqs.pdf', [
            'rfq' => $rfq,
            'companyLogoDataUri' => $companyLogoDataUri,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $path = 'rfqs/'.$rfq->company_id.'/'.$rfq->id.'/pdf/'.Str::slug($rfq->number).'-'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        if ($rfq->pdf_path && $rfq->pdf_path !== $path) {
            $this->delete($rfq->pdf_path);
        }

        $rfq->forceFill(['pdf_path' => $path])->save();

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

