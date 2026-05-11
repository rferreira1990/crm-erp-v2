<?php

namespace App\Services\Admin;

use App\Models\SupplierPayment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupplierPaymentPdfService
{
    public function generateAndStore(SupplierPayment $payment): string
    {
        $payment->loadMissing([
            'company:id,name,nif,address,postal_code,locality,city,email,phone,mobile,logo_path,pdf_layout',
            'supplier:id,name,nif,email,phone,mobile,address,postal_code,locality,city',
            'purchaseDocument:id,number,issue_date,due_date,currency,grand_total,payment_status',
            'paymentMethod:id,name',
            'creator:id,name',
            'canceller:id,name',
        ]);

        $companyLogoDataUri = $this->companyLogoDataUri($payment->company?->logo_path);

        $layout = (string) ($payment->company?->pdf_layout ?? 'classic');
        $template = $layout === 'modern'
            ? 'admin.supplier-payments.pdf-modern'
            : 'admin.supplier-payments.pdf';

        $html = view($template, [
            'payment' => $payment,
            'companyLogoDataUri' => $companyLogoDataUri,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $path = 'supplier-payments/'.$payment->company_id.'/'.$payment->id.'/pdf/'
            .Str::slug($payment->number).'-'.now()->format('YmdHis').'.pdf';

        Storage::disk('local')->put($path, $pdf->output());

        if ($payment->pdf_path && $payment->pdf_path !== $path) {
            $this->delete($payment->pdf_path);
        }

        $payment->forceFill(['pdf_path' => $path])->save();

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
