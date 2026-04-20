<?php

namespace App\Services\Admin;

use App\Models\Quote;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuotePdfService
{
    public function generateAndStore(Quote $quote): string
    {
        $quote->loadMissing([
            'company:id,name,nif,address,postal_code,locality,city,email,phone,mobile,website,bank_name,iban,bic_swift,logo_path',
            'customer:id,name,nif,email,phone,mobile,address,postal_code,locality,city',
            'customerContact:id,customer_id,name,email,phone,job_title',
            'paymentTerm:id,name',
            'paymentMethod:id,name',
            'items' => fn ($query) => $query
                ->with([
                    'unit:id,code,name',
                    'vatRate:id,name,rate,is_exempt',
                    'vatExemptionReason:id,code,name',
                ])
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        $companyLogoDataUri = $this->companyLogoDataUri($quote->company?->logo_path);

        $html = view('admin.quotes.pdf', [
            'quote' => $quote,
            'companyLogoDataUri' => $companyLogoDataUri,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $path = 'quotes/'.$quote->company_id.'/'.$quote->id.'/pdf/'.Str::slug($quote->number).'-'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        if ($quote->pdf_path && $quote->pdf_path !== $path) {
            $this->delete($quote->pdf_path);
        }

        $quote->forceFill(['pdf_path' => $path])->save();

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
