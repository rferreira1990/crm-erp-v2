<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierContactRequest;
use App\Http\Requests\Admin\UpdateSupplierContactRequest;
use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierContactController extends Controller
{
    public function create(Request $request, int $supplier): View
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->authorize('create', SupplierContact::class);
        $this->ensureCompanySupplierOrFail($supplierModel);

        return view('admin.suppliers.contacts.create', [
            'supplier' => $supplierModel,
        ]);
    }

    public function store(StoreSupplierContactRequest $request, int $supplier): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->authorize('create', SupplierContact::class);
        $this->ensureCompanySupplierOrFail($supplierModel);

        $data = $request->validated();

        $contact = DB::transaction(function () use ($companyId, $supplierModel, $data): SupplierContact {
            $this->lockContacts($companyId, (int) $supplierModel->id);

            $hasAnyContacts = SupplierContact::query()
                ->forCompany($companyId)
                ->forSupplier((int) $supplierModel->id)
                ->exists();

            $isPrimary = ! $hasAnyContacts || (bool) ($data['is_primary'] ?? false);

            if ($isPrimary) {
                SupplierContact::query()
                    ->forCompany($companyId)
                    ->forSupplier((int) $supplierModel->id)
                    ->update(['is_primary' => false]);
            }

            return SupplierContact::query()->create([
                'company_id' => $companyId,
                'supplier_id' => (int) $supplierModel->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_primary' => $isPrimary,
            ]);
        });

        Log::info('Supplier contact created', [
            'context' => 'company_suppliers_contacts',
            'company_id' => $companyId,
            'supplier_id' => $supplierModel->id,
            'supplier_contact_id' => $contact->id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.suppliers.edit', $supplierModel->id)
            ->with('status', 'Contacto criado com sucesso.');
    }

    public function edit(Request $request, int $supplier, int $contact): View
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->ensureCompanySupplierOrFail($supplierModel);

        $contactModel = $this->findCompanySupplierContactOrFail($companyId, (int) $supplierModel->id, $contact);
        $this->authorize('update', $contactModel);

        return view('admin.suppliers.contacts.edit', [
            'supplier' => $supplierModel,
            'contact' => $contactModel,
        ]);
    }

    public function update(UpdateSupplierContactRequest $request, int $supplier, int $contact): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $this->ensureCompanySupplierOrFail($supplierModel);

        $contactModel = $this->findCompanySupplierContactOrFail($companyId, (int) $supplierModel->id, $contact);
        $this->authorize('update', $contactModel);
        $data = $request->validated();

        DB::transaction(function () use ($companyId, $supplierModel, $contactModel, $data): void {
            $this->lockContacts($companyId, (int) $supplierModel->id);

            $hasOtherContacts = SupplierContact::query()
                ->forCompany($companyId)
                ->forSupplier((int) $supplierModel->id)
                ->whereKeyNot($contactModel->id)
                ->exists();

            $isPrimary = (bool) ($data['is_primary'] ?? false);

            if (! $hasOtherContacts) {
                $isPrimary = true;
            }

            if ($isPrimary) {
                SupplierContact::query()
                    ->forCompany($companyId)
                    ->forSupplier((int) $supplierModel->id)
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
                $hasPrimary = SupplierContact::query()
                    ->forCompany($companyId)
                    ->forSupplier((int) $supplierModel->id)
                    ->where('is_primary', true)
                    ->exists();

                if (! $hasPrimary) {
                    $fallback = SupplierContact::query()
                        ->forCompany($companyId)
                        ->forSupplier((int) $supplierModel->id)
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

        Log::info('Supplier contact updated', [
            'context' => 'company_suppliers_contacts',
            'company_id' => $companyId,
            'supplier_id' => $supplierModel->id,
            'supplier_contact_id' => $contactModel->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.suppliers.edit', $supplierModel->id)
            ->with('status', 'Contacto atualizado com sucesso.');
    }

    public function destroy(Request $request, int $supplier, int $contact): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $supplierModel = $this->findCompanySupplierOrFail($companyId, $supplier);
        $contactModel = $this->findCompanySupplierContactOrFail($companyId, (int) $supplierModel->id, $contact);
        $this->authorize('delete', $contactModel);

        DB::transaction(function () use ($companyId, $supplierModel, $contactModel): void {
            $this->lockContacts($companyId, (int) $supplierModel->id);

            $deletedWasPrimary = (bool) $contactModel->is_primary;
            $contactModel->delete();

            if (! $deletedWasPrimary) {
                return;
            }

            $hasPrimary = SupplierContact::query()
                ->forCompany($companyId)
                ->forSupplier((int) $supplierModel->id)
                ->where('is_primary', true)
                ->exists();

            if ($hasPrimary) {
                return;
            }

            $fallback = SupplierContact::query()
                ->forCompany($companyId)
                ->forSupplier((int) $supplierModel->id)
                ->orderBy('id')
                ->first();

            if ($fallback) {
                $fallback->forceFill(['is_primary' => true])->save();
            }
        });

        Log::info('Supplier contact deleted', [
            'context' => 'company_suppliers_contacts',
            'company_id' => $companyId,
            'supplier_id' => $supplierModel->id,
            'supplier_contact_id' => $contactModel->id,
            'deleted_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.suppliers.edit', $supplierModel->id)
            ->with('status', 'Contacto removido com sucesso.');
    }

    private function findCompanySupplierOrFail(int $companyId, int $supplierId): Supplier
    {
        return Supplier::query()
            ->forCompany($companyId)
            ->whereKey($supplierId)
            ->firstOrFail();
    }

    private function findCompanySupplierContactOrFail(int $companyId, int $supplierId, int $contactId): SupplierContact
    {
        return SupplierContact::query()
            ->forCompany($companyId)
            ->forSupplier($supplierId)
            ->whereKey($contactId)
            ->firstOrFail();
    }

    private function ensureCompanySupplierOrFail(Supplier $supplier): void
    {
        if ($supplier->supplier_type !== Supplier::TYPE_COMPANY) {
            abort(404);
        }
    }

    private function lockContacts(int $companyId, int $supplierId): void
    {
        SupplierContact::query()
            ->forCompany($companyId)
            ->forSupplier($supplierId)
            ->lockForUpdate()
            ->get(['id']);
    }
}
