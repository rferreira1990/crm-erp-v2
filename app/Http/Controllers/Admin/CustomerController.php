<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\VatRate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->forCompany($companyId)
            ->with([
                'country:id,name,iso_code',
                'paymentTerm:id,name',
                'defaultVatRate:id,name,rate',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('nif', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('mobile', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'filters' => [
                'q' => $search,
            ],
            'customerTypeLabels' => Customer::customerTypeLabels(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Customer::class);

        $companyId = (int) $request->user()->company_id;

        return view('admin.customers.create', [
            'defaults' => [
                'country_id' => Customer::defaultCountryId(),
                'payment_term_id' => Customer::defaultPaymentTermIdForCompany($companyId),
                'is_active' => true,
            ],
            ...$this->buildFormOptions($companyId),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $data = $this->normalizePayload($request->validated());
        $newLogo = $request->file('logo');

        $customer = Customer::query()->create([
            ...$data,
            'company_id' => $companyId,
        ]);

        $this->syncCustomerLogo($customer, $newLogo, false, $companyId);

        Log::info('Customer created', [
            'context' => 'company_customers',
            'customer_id' => $customer->id,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.customers.index')
            ->with('status', 'Cliente criado com sucesso.');
    }

    public function edit(Request $request, int $customer): View
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->authorize('update', $customerModel);

        return view('admin.customers.edit', [
            'customer' => $customerModel,
            ...$this->buildFormOptions($companyId, $customerModel->payment_term_id, $customerModel->default_vat_rate_id),
        ]);
    }

    public function update(UpdateCustomerRequest $request, int $customer): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->authorize('update', $customerModel);

        $validated = $request->validated();
        $removeLogo = (bool) ($validated['remove_logo'] ?? false);
        $newLogo = $request->file('logo');
        $data = $this->normalizePayload($validated);

        $customerModel->forceFill($data)->save();
        $this->syncCustomerLogo($customerModel, $newLogo, $removeLogo, $companyId);

        Log::info('Customer updated', [
            'context' => 'company_customers',
            'customer_id' => $customerModel->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.customers.edit', $customerModel->id)
            ->with('status', 'Cliente atualizado com sucesso.');
    }

    public function destroy(Request $request, int $customer): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->authorize('delete', $customerModel);

        if ($customerModel->logo_path) {
            $this->deleteFromDisk($customerModel->logo_path);
        }

        $customerModel->delete();

        Log::info('Customer deleted', [
            'context' => 'company_customers',
            'customer_id' => $customerModel->id,
            'company_id' => $companyId,
            'deleted_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.customers.index')
            ->with('status', 'Cliente eliminado com sucesso.');
    }

    public function showLogo(Request $request, int $customer): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->authorize('view', $customerModel);

        if (! $customerModel->logo_path) {
            abort(404);
        }

        return Storage::disk('local')->response(
            $customerModel->logo_path,
            'customer-'.$customerModel->id.'-logo.'.pathinfo($customerModel->logo_path, PATHINFO_EXTENSION)
        );
    }

    /**
     * @return array{
     *   countries: Collection<int, Country>,
     *   paymentTermOptions: Collection<int, PaymentTerm>,
     *   vatRateOptions: Collection<int, VatRate>,
     *   customerTypeOptions: array<string, string>
     * }
     */
    private function buildFormOptions(
        int $companyId,
        ?int $includePaymentTermId = null,
        ?int $includeVatRateId = null
    ): array {
        return [
            'countries' => Country::query()
                ->orderByRaw("CASE WHEN iso_code = 'PT' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'iso_code']),
            'paymentTermOptions' => $this->visiblePaymentTerms($companyId, $includePaymentTermId),
            'vatRateOptions' => $this->enabledVatRates($companyId, $includeVatRateId),
            'customerTypeOptions' => Customer::customerTypeLabels(),
        ];
    }

    /**
     * @return Collection<int, PaymentTerm>
     */
    private function visiblePaymentTerms(int $companyId, ?int $includePaymentTermId = null): Collection
    {
        return PaymentTerm::query()
            ->visibleToCompany($companyId)
            ->when($includePaymentTermId !== null, function ($query) use ($includePaymentTermId): void {
                $query->orWhereKey($includePaymentTermId);
            })
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->orderBy('name')
            ->get(['id', 'name', 'company_id']);
    }

    /**
     * @return Collection<int, VatRate>
     */
    private function enabledVatRates(int $companyId, ?int $includeVatRateId = null): Collection
    {
        return VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->get(['id', 'name', 'region', 'rate', 'is_exempt'])
            ->filter(function (VatRate $vatRate) use ($companyId, $includeVatRateId): bool {
                if ($includeVatRateId !== null && $vatRate->id === $includeVatRateId) {
                    return true;
                }

                return $vatRate->isEnabledForCompany($companyId);
            })
            ->sortBy([
                ['region', 'asc'],
                ['is_exempt', 'asc'],
                ['rate', 'desc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        if (! (bool) ($data['has_credit_limit'] ?? false)) {
            $data['credit_limit'] = null;
        }

        unset($data['remove_logo']);
        unset($data['logo']);

        return $data;
    }

    private function findCompanyCustomerOrFail(int $companyId, int $customerId): Customer
    {
        return Customer::query()
            ->forCompany($companyId)
            ->whereKey($customerId)
            ->firstOrFail();
    }

    private function storeCustomerLogo(?UploadedFile $logo, int $companyId, int $customerId): ?string
    {
        if (! $logo) {
            return null;
        }

        return $logo->storeAs(
            'customers/'.$companyId.'/'.$customerId.'/logo',
            Str::uuid()->toString().'.'.$logo->getClientOriginalExtension(),
            'local'
        );
    }

    private function syncCustomerLogo(Customer $customer, ?UploadedFile $newLogo, bool $removeLogo, int $companyId): void
    {
        // Business rule: when upload and remove flag are both provided, new upload prevails.
        if ($newLogo instanceof UploadedFile) {
            $previousPath = $customer->logo_path;
            $newPath = $this->storeCustomerLogo($newLogo, $companyId, (int) $customer->id);

            if ($newPath !== null) {
                $customer->forceFill(['logo_path' => $newPath])->save();
            }

            if ($previousPath) {
                $this->deleteFromDisk($previousPath);
            }

            return;
        }

        if ($removeLogo && $customer->logo_path) {
            $this->deleteFromDisk($customer->logo_path);
            $customer->forceFill(['logo_path' => null])->save();
        }
    }

    private function deleteFromDisk(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('local')->delete($path);
    }
}
