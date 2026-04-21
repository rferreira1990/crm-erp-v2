<?php

namespace App\Services\Admin;

use App\Models\PurchaseOrder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PurchaseOrderPdfService
{
    public function generateAndStore(PurchaseOrder $purchaseOrder): string
    {
        $purchaseOrder->loadMissing([
            'company:id,name,nif,address,postal_code,locality,city,email,phone,mobile,website,logo_path',
            'creator:id,name',
            'assignedUser:id,name',
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
        ]);

        $companyLogoDataUri = $this->companyLogoDataUri($purchaseOrder->company?->logo_path);

        $html = view('admin.purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
            'companyLogoDataUri' => $companyLogoDataUri,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $path = 'purchase-orders/'.$purchaseOrder->company_id.'/'.$purchaseOrder->id.'/pdf/'.Str::slug($purchaseOrder->number).'-'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        if ($purchaseOrder->pdf_path && $purchaseOrder->pdf_path !== $path) {
            $this->delete($purchaseOrder->pdf_path);
        }

        $purchaseOrder->forceFill(['pdf_path' => $path])->save();

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

