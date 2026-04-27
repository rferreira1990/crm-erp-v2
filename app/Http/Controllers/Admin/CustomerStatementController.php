<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendCustomerStatementEmailRequest;
use App\Mail\Admin\CustomerStatementMail;
use App\Models\Customer;
use App\Services\Admin\CompanyMailSettingsService;
use App\Services\Admin\CustomerStatementService;
use App\Services\Admin\CustomerStatementPdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CustomerStatementController extends Controller
{
    public function __construct(
        private readonly CustomerStatementService $customerStatementService,
        private readonly CustomerStatementPdfService $customerStatementPdfService,
        private readonly CompanyMailSettingsService $companyMailSettingsService
    ) {
    }

    public function show(Request $request, int $customer): View
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $filters = $this->extractFilters($request);

        $this->authorize('viewStatement', $customerModel);

        $statement = $this->customerStatementService->buildStatement(
            companyId: $companyId,
            customerId: (int) $customerModel->id,
            filters: $filters
        );
        $statement['customer']->loadMissing('company');

        return view('admin.customers.statement', [
            'customer' => $statement['customer'],
            'movements' => $statement['movements'],
            'totalDebit' => $statement['total_debit'],
            'totalCredit' => $statement['total_credit'],
            'balance' => $statement['balance'],
            'periodLabel' => $statement['period_label'],
            'filters' => $statement['filters'],
        ]);
    }

    public function downloadPdf(Request $request, int $customer): Response
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->authorize('pdfStatement', $customerModel);

        $statement = $this->customerStatementService->buildStatement(
            companyId: $companyId,
            customerId: (int) $customerModel->id,
            filters: $this->extractFilters($request)
        );

        $pdfBytes = $this->customerStatementPdfService->render($customerModel, $statement);
        $filename = 'extrato-conta-corrente-'.\Illuminate\Support\Str::slug($customerModel->name).'.pdf';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function sendEmail(SendCustomerStatementEmailRequest $request, int $customer): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->authorize('sendStatement', $customerModel);

        $filters = [
            'date_from' => $request->validated('date_from'),
            'date_to' => $request->validated('date_to'),
        ];

        $statement = $this->customerStatementService->buildStatement(
            companyId: $companyId,
            customerId: (int) $customerModel->id,
            filters: $filters
        );

        $customerModel->loadMissing('company');
        $this->companyMailSettingsService->applyRuntimeConfig($customerModel->company);

        $pdfBytes = $this->customerStatementPdfService->render($customerModel, $statement);
        $filename = 'extrato-conta-corrente-'.\Illuminate\Support\Str::slug($customerModel->name).'.pdf';

        $to = $request->validated('to');
        $ccRecipients = $request->ccRecipients();
        $subject = $request->validated('subject');
        $message = $request->validated('message');

        $mailer = Mail::to($to);
        if ($ccRecipients !== []) {
            $mailer->cc($ccRecipients);
        }

        try {
            $mailer->send(new CustomerStatementMail(
                company: $customerModel->company,
                customer: $customerModel,
                pdfBytes: $pdfBytes,
                pdfFilename: $filename,
                subjectLine: $subject,
                messageBody: $message,
                periodLabel: (string) $statement['period_label'],
                balance: (float) $statement['balance'],
                totalDebit: (float) $statement['total_debit'],
                totalCredit: (float) $statement['total_credit'],
            ));
        } catch (Throwable $exception) {
            Log::warning('Customer statement email send failed', [
                'context' => 'customer_statement',
                'customer_id' => (int) $customerModel->id,
                'company_id' => (int) $customerModel->company_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.customers.statement.show', [
                    'customer' => $customerModel->id,
                    ...array_filter($statement['filters']),
                ])
                ->withInput()
                ->withErrors([
                    'customer_statement_email' => $this->friendlyEmailError($exception),
                ]);
        }

        return redirect()
            ->route('admin.customers.statement.show', [
                'customer' => $customerModel->id,
                ...array_filter($statement['filters']),
            ])
            ->with('status', 'Extrato enviado por email com sucesso.');
    }

    private function findCompanyCustomerOrFail(int $companyId, int $customerId): Customer
    {
        return Customer::query()
            ->forCompany($companyId)
            ->whereKey($customerId)
            ->firstOrFail();
    }

    /**
     * @return array{date_from: ?string, date_to: ?string}
     */
    private function extractFilters(Request $request): array
    {
        return [
            'date_from' => $this->normalizeDateFilter((string) $request->query('date_from', '')),
            'date_to' => $this->normalizeDateFilter((string) $request->query('date_to', '')),
        ];
    }

    private function normalizeDateFilter(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) === 1
            ? $normalized
            : null;
    }

    private function friendlyEmailError(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        if ($exception instanceof TransportExceptionInterface) {
            if (str_contains($message, 'auth') || str_contains($message, '535') || str_contains($message, 'username') || str_contains($message, 'password')) {
                return 'Falha de autenticacao SMTP. Verifique username e password.';
            }

            if (str_contains($message, 'connection') || str_contains($message, 'timed out') || str_contains($message, 'refused') || str_contains($message, 'getaddrinfo') || str_contains($message, 'network')) {
                return 'Falha de ligacao SMTP. Verifique host, porta e encriptacao.';
            }
        }

        return 'Falha no envio do Extrato por email. Verifique a configuracao SMTP e tente novamente.';
    }
}
