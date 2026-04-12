<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $paymentMethods = PaymentMethod::query()
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payment-methods.index', [
            'paymentMethods' => $paymentMethods,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PaymentMethod::class);

        return view('admin.payment-methods.create');
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $request->user()->company_id,
            'is_system' => false,
            'name' => $data['name'],
        ]);

        Log::info('Company payment method created', [
            'context' => 'company_payment_methods',
            'payment_method_id' => $paymentMethod->id,
            'company_id' => $paymentMethod->company_id,
            'created_by' => $request->user()->id,
            'name' => $paymentMethod->name,
        ]);

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('status', 'Modo de pagamento criado com sucesso.');
    }

    public function edit(Request $request, int $paymentMethod): View
    {
        $companyId = (int) $request->user()->company_id;
        $method = $this->findVisiblePaymentMethodOrFail($companyId, $paymentMethod);
        $this->authorize('update', $method);

        return view('admin.payment-methods.edit', [
            'paymentMethod' => $method,
        ]);
    }

    public function update(UpdatePaymentMethodRequest $request, int $paymentMethod): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $method = $this->findVisiblePaymentMethodOrFail($companyId, $paymentMethod);
        $this->authorize('update', $method);

        $method->forceFill($request->validated())->save();

        Log::info('Company payment method updated', [
            'context' => 'company_payment_methods',
            'payment_method_id' => $method->id,
            'company_id' => $method->company_id,
            'updated_by' => $request->user()->id,
            'name' => $method->name,
        ]);

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('status', 'Modo de pagamento atualizado com sucesso.');
    }

    public function destroy(Request $request, int $paymentMethod): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $method = $this->findVisiblePaymentMethodOrFail($companyId, $paymentMethod);
        $this->authorize('delete', $method);

        if ($this->isPaymentMethodInUse($method)) {
            return back()->withErrors([
                'payment_method' => 'Não é possível eliminar o modo de pagamento porque está em uso.',
            ]);
        }

        $method->delete();

        Log::info('Company payment method deleted', [
            'context' => 'company_payment_methods',
            'payment_method_id' => $method->id,
            'company_id' => $method->company_id,
            'deleted_by' => $request->user()->id,
            'name' => $method->name,
        ]);

        return redirect()
            ->route('admin.payment-methods.index')
            ->with('status', 'Modo de pagamento eliminado com sucesso.');
    }

    private function findVisiblePaymentMethodOrFail(int $companyId, int $paymentMethodId): PaymentMethod
    {
        return PaymentMethod::query()
            ->visibleToCompany($companyId)
            ->whereKey($paymentMethodId)
            ->firstOrFail();
    }

    private function isPaymentMethodInUse(PaymentMethod $paymentMethod): bool
    {
        // Extension point: block delete when invoices/quotes start referencing payment methods.
        return false;
    }
}
