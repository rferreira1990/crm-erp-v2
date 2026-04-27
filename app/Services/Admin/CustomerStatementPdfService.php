<?php

namespace App\Services\Admin;

use App\Models\Customer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class CustomerStatementPdfService
{
    /**
     * @param array<string, mixed> $statement
     */
    public function render(Customer $customer, array $statement): string
    {
        $customer->loadMissing([
            'company:id,name,nif,address,postal_code,locality,city,email,phone,mobile,logo_path',
        ]);

        $html = view('admin.customers.statement-pdf', [
            'customer' => $customer,
            'companyLogoDataUri' => $this->companyLogoDataUri((string) ($customer->company?->logo_path ?? '')),
            'movements' => $statement['movements'],
            'totalDebit' => $statement['total_debit'],
            'totalCredit' => $statement['total_credit'],
            'balance' => $statement['balance'],
            'periodLabel' => (string) ($statement['period_label'] ?? 'Periodo: completo'),
            'generatedAt' => now(),
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    private function companyLogoDataUri(string $path): ?string
    {
        $normalizedPath = trim($path);
        if ($normalizedPath === '' || ! Storage::disk('local')->exists($normalizedPath)) {
            return null;
        }

        $contents = Storage::disk('local')->get($normalizedPath);
        if ($contents === '') {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($normalizedPath);
        if (! is_string($mime) || ! str_starts_with($mime, 'image/')) {
            $mime = 'image/png';
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
