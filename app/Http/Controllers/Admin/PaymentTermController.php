<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentTermRequest;
use App\Http\Requests\Admin\UpdatePaymentTermRequest;
use App\Models\CompanyPaymentTermOverride;
use App\Models\PaymentTerm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentTermController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PaymentTerm::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $paymentTerms = PaymentTerm::query()
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderByDesc('is_system')
            ->orderBy('calculation_type')
            ->orderBy('days')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $disabledSystemTerms = PaymentTerm::query()
            ->where('is_system', true)
            ->whereNull('company_id')
            ->whereExists(function ($query) use ($companyId): void {
                $query->selectRaw('1')
                    ->from('company_payment_term_overrides as cpto')
                    ->whereColumn('cpto.payment_term_id', 'payment_terms.id')
                    ->where('cpto.company_id', $companyId)
                    ->where('cpto.is_enabled', false);
            })
            ->orderBy('name')
            ->get();

        return view('admin.payment-terms.index', [
            'paymentTerms' => $paymentTerms,
            'disabledSystemTerms' => $disabledSystemTerms,
            'canManageDefaults' => $request->user()->can('manageDefaults', PaymentTerm::class),
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PaymentTerm::class);

        return view('admin.payment-terms.create', [
            'calculationTypeOptions' => PaymentTerm::calculationTypeLabels(),
        ]);
    }

    public function store(StorePaymentTermRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $paymentTerm = PaymentTerm::query()->create([
            'company_id' => $request->user()->company_id,
            'name' => $data['name'],
            'calculation_type' => $data['calculation_type'],
            'days' => (int) $data['days'],
            'is_system' => false,
        ]);

        Log::info('Company payment term created', [
            'context' => 'company_payment_terms',
            'payment_term_id' => $paymentTerm->id,
            'company_id' => $paymentTerm->company_id,
            'created_by' => $request->user()->id,
            'name' => $paymentTerm->name,
            'calculation_type' => $paymentTerm->calculation_type,
            'days' => $paymentTerm->days,
        ]);

        return redirect()
            ->route('admin.payment-terms.index')
            ->with('status', 'Condição de pagamento criada com sucesso.');
    }

    public function edit(Request $request, int $paymentTerm): View
    {
        $companyId = (int) $request->user()->company_id;
        $term = $this->findVisiblePaymentTermOrFail($companyId, $paymentTerm);
        $this->authorize('update', $term);

        return view('admin.payment-terms.edit', [
            'paymentTerm' => $term,
            'calculationTypeOptions' => PaymentTerm::calculationTypeLabels(),
        ]);
    }

    public function update(UpdatePaymentTermRequest $request, int $paymentTerm): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $term = $this->findVisiblePaymentTermOrFail($companyId, $paymentTerm);
        $this->authorize('update', $term);

        $term->forceFill($request->validated())->save();

        Log::info('Company payment term updated', [
            'context' => 'company_payment_terms',
            'payment_term_id' => $term->id,
            'company_id' => $term->company_id,
            'updated_by' => $request->user()->id,
            'name' => $term->name,
            'calculation_type' => $term->calculation_type,
            'days' => $term->days,
        ]);

        return redirect()
            ->route('admin.payment-terms.index')
            ->with('status', 'Condição de pagamento atualizada com sucesso.');
    }

    public function destroy(Request $request, int $paymentTerm): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $term = $this->findVisiblePaymentTermOrFail($companyId, $paymentTerm);
        $this->authorize('delete', $term);

        if ($this->isPaymentTermInUse($term)) {
            return back()->withErrors([
                'payment_term' => 'Não é possível eliminar a condição de pagamento porque está em uso.',
            ]);
        }

        $term->delete();

        Log::info('Company payment term deleted', [
            'context' => 'company_payment_terms',
            'payment_term_id' => $term->id,
            'company_id' => $term->company_id,
            'deleted_by' => $request->user()->id,
            'name' => $term->name,
        ]);

        return redirect()
            ->route('admin.payment-terms.index')
            ->with('status', 'Condição de pagamento eliminada com sucesso.');
    }

    public function deactivateSystemTerm(Request $request, int $paymentTerm): RedirectResponse
    {
        $this->authorize('manageDefaults', PaymentTerm::class);

        $companyId = (int) $request->user()->company_id;
        $systemTerm = $this->findSystemPaymentTermOrFail($paymentTerm);

        CompanyPaymentTermOverride::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'payment_term_id' => $systemTerm->id,
            ],
            [
                'is_enabled' => false,
            ]
        );

        Log::info('System payment term disabled for company context', [
            'context' => 'company_payment_terms',
            'payment_term_id' => $systemTerm->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
            'is_enabled' => false,
        ]);

        return redirect()
            ->route('admin.payment-terms.index')
            ->with('status', 'Condição de pagamento do sistema desativada com sucesso.');
    }

    public function reactivateSystemTerm(Request $request, int $paymentTerm): RedirectResponse
    {
        $this->authorize('manageDefaults', PaymentTerm::class);

        $companyId = (int) $request->user()->company_id;
        $systemTerm = $this->findSystemPaymentTermOrFail($paymentTerm);

        CompanyPaymentTermOverride::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'payment_term_id' => $systemTerm->id,
            ],
            [
                'is_enabled' => true,
            ]
        );

        Log::info('System payment term re-enabled for company context', [
            'context' => 'company_payment_terms',
            'payment_term_id' => $systemTerm->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
            'is_enabled' => true,
        ]);

        return redirect()
            ->route('admin.payment-terms.index')
            ->with('status', 'Condição de pagamento do sistema reativada com sucesso.');
    }

    private function findVisiblePaymentTermOrFail(int $companyId, int $paymentTermId): PaymentTerm
    {
        return PaymentTerm::query()
            ->visibleToCompany($companyId)
            ->whereKey($paymentTermId)
            ->firstOrFail();
    }

    private function findSystemPaymentTermOrFail(int $paymentTermId): PaymentTerm
    {
        return PaymentTerm::query()
            ->where('is_system', true)
            ->whereNull('company_id')
            ->whereKey($paymentTermId)
            ->firstOrFail();
    }

    private function isPaymentTermInUse(PaymentTerm $paymentTerm): bool
    {
        // Extension point: block delete when invoices/quotes start referencing payment terms.
        return false;
    }
}
