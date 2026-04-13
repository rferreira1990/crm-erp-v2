<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerContactRequest;
use App\Http\Requests\Admin\UpdateCustomerContactRequest;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerContactController extends Controller
{
    public function create(Request $request, int $customer): View
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->authorize('create', CustomerContact::class);
        $this->ensureCompanyCustomerOrFail($customerModel);

        return view('admin.customers.contacts.create', [
            'customer' => $customerModel,
        ]);
    }

    public function store(StoreCustomerContactRequest $request, int $customer): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->authorize('create', CustomerContact::class);
        $this->ensureCompanyCustomerOrFail($customerModel);

        $data = $request->validated();

        $contact = DB::transaction(function () use ($companyId, $customerModel, $data): CustomerContact {
            $this->lockContacts($companyId, (int) $customerModel->id);

            $hasAnyContacts = CustomerContact::query()
                ->forCompany($companyId)
                ->forCustomer((int) $customerModel->id)
                ->exists();

            $isPrimary = ! $hasAnyContacts || (bool) $data['is_primary'];

            if ($isPrimary) {
                CustomerContact::query()
                    ->forCompany($companyId)
                    ->forCustomer((int) $customerModel->id)
                    ->update(['is_primary' => false]);
            }

            return CustomerContact::query()->create([
                'company_id' => $companyId,
                'customer_id' => (int) $customerModel->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_primary' => $isPrimary,
            ]);
        });

        Log::info('Customer contact created', [
            'context' => 'company_customers_contacts',
            'company_id' => $companyId,
            'customer_id' => $customerModel->id,
            'customer_contact_id' => $contact->id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.customers.edit', $customerModel->id)
            ->with('status', 'Contacto criado com sucesso.');
    }

    public function edit(Request $request, int $customer, int $contact): View
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->ensureCompanyCustomerOrFail($customerModel);

        $contactModel = $this->findCompanyCustomerContactOrFail($companyId, (int) $customerModel->id, $contact);
        $this->authorize('update', $contactModel);

        return view('admin.customers.contacts.edit', [
            'customer' => $customerModel,
            'contact' => $contactModel,
        ]);
    }

    public function update(UpdateCustomerContactRequest $request, int $customer, int $contact): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $this->ensureCompanyCustomerOrFail($customerModel);

        $contactModel = $this->findCompanyCustomerContactOrFail($companyId, (int) $customerModel->id, $contact);
        $this->authorize('update', $contactModel);
        $data = $request->validated();

        DB::transaction(function () use ($companyId, $customerModel, $contactModel, $data): void {
            $this->lockContacts($companyId, (int) $customerModel->id);

            $hasOtherContacts = CustomerContact::query()
                ->forCompany($companyId)
                ->forCustomer((int) $customerModel->id)
                ->whereKeyNot($contactModel->id)
                ->exists();

            $isPrimary = (bool) $data['is_primary'];

            if (! $hasOtherContacts) {
                $isPrimary = true;
            }

            if ($isPrimary) {
                CustomerContact::query()
                    ->forCompany($companyId)
                    ->forCustomer((int) $customerModel->id)
                    ->whereKeyNot($contactModel->id)
                    ->update(['is_primary' => false]);
            }

            $contactModel->forceFill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_primary' => $isPrimary,
            ])->save();

            if (! $isPrimary) {
                $hasPrimary = CustomerContact::query()
                    ->forCompany($companyId)
                    ->forCustomer((int) $customerModel->id)
                    ->where('is_primary', true)
                    ->exists();

                if (! $hasPrimary) {
                    $fallback = CustomerContact::query()
                        ->forCompany($companyId)
                        ->forCustomer((int) $customerModel->id)
                        ->whereKeyNot($contactModel->id)
                        ->orderBy('id')
                        ->first();

                    if ($fallback) {
                        $fallback->forceFill(['is_primary' => true])->save();
                    } else {
                        $contactModel->forceFill(['is_primary' => true])->save();
                    }
                }
            }
        });

        Log::info('Customer contact updated', [
            'context' => 'company_customers_contacts',
            'company_id' => $companyId,
            'customer_id' => $customerModel->id,
            'customer_contact_id' => $contactModel->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.customers.edit', $customerModel->id)
            ->with('status', 'Contacto atualizado com sucesso.');
    }

    public function destroy(Request $request, int $customer, int $contact): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $customerModel = $this->findCompanyCustomerOrFail($companyId, $customer);
        $contactModel = $this->findCompanyCustomerContactOrFail($companyId, (int) $customerModel->id, $contact);
        $this->authorize('delete', $contactModel);

        DB::transaction(function () use ($companyId, $customerModel, $contactModel): void {
            $this->lockContacts($companyId, (int) $customerModel->id);

            $deletedWasPrimary = (bool) $contactModel->is_primary;
            $contactModel->delete();

            if (! $deletedWasPrimary) {
                return;
            }

            $hasPrimary = CustomerContact::query()
                ->forCompany($companyId)
                ->forCustomer((int) $customerModel->id)
                ->where('is_primary', true)
                ->exists();

            if ($hasPrimary) {
                return;
            }

            $fallback = CustomerContact::query()
                ->forCompany($companyId)
                ->forCustomer((int) $customerModel->id)
                ->orderBy('id')
                ->first();

            if ($fallback) {
                $fallback->forceFill(['is_primary' => true])->save();
            }
        });

        Log::info('Customer contact deleted', [
            'context' => 'company_customers_contacts',
            'company_id' => $companyId,
            'customer_id' => $customerModel->id,
            'customer_contact_id' => $contactModel->id,
            'deleted_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.customers.edit', $customerModel->id)
            ->with('status', 'Contacto removido com sucesso.');
    }

    private function findCompanyCustomerOrFail(int $companyId, int $customerId): Customer
    {
        return Customer::query()
            ->forCompany($companyId)
            ->whereKey($customerId)
            ->firstOrFail();
    }

    private function findCompanyCustomerContactOrFail(int $companyId, int $customerId, int $contactId): CustomerContact
    {
        return CustomerContact::query()
            ->forCompany($companyId)
            ->forCustomer($customerId)
            ->whereKey($contactId)
            ->firstOrFail();
    }

    private function ensureCompanyCustomerOrFail(Customer $customer): void
    {
        if ($customer->customer_type !== Customer::TYPE_COMPANY) {
            abort(404);
        }
    }

    private function lockContacts(int $companyId, int $customerId): void
    {
        CustomerContact::query()
            ->forCompany($companyId)
            ->forCustomer($customerId)
            ->lockForUpdate()
            ->get(['id']);
    }
}
