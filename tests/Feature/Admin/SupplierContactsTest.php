<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierContactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_access_is_blocked_with_not_found(): void
    {
        $companyA = $this->createCompany('Empresa Fornecedores Contactos A');
        $companyB = $this->createCompany('Empresa Fornecedores Contactos B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $supplierB = Supplier::query()->create([
            'company_id' => $companyB->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor B',
            'is_active' => true,
        ]);

        $contactB = SupplierContact::query()->create([
            'company_id' => $companyB->id,
            'supplier_id' => $supplierB->id,
            'name' => 'Contacto B',
            'is_primary' => true,
        ]);

        $this->actingAs($adminA)
            ->get(route('admin.suppliers.contacts.create', $supplierB->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.suppliers.contacts.store', $supplierB->id), [
                'name' => 'Novo contacto',
                'is_primary' => 1,
            ])
            ->assertNotFound();

        $this->actingAs($adminA)
            ->get(route('admin.suppliers.contacts.edit', [$supplierB->id, $contactB->id]))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->delete(route('admin.suppliers.contacts.destroy', [$supplierB->id, $contactB->id]))
            ->assertNotFound();
    }

    public function test_only_company_suppliers_can_create_and_edit_contacts(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Contactos Tipo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $individualSupplier = Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_INDIVIDUAL,
            'name' => 'Fornecedor Individual',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.suppliers.contacts.create', $individualSupplier->id))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.suppliers.contacts.store', $individualSupplier->id), [
                'name' => 'Contacto Invalido',
                'is_primary' => 1,
            ])
            ->assertNotFound();

        $contact = SupplierContact::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $individualSupplier->id,
            'name' => 'Contacto Antigo',
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.suppliers.contacts.edit', [$individualSupplier->id, $contact->id]))
            ->assertNotFound();
    }

    public function test_first_contact_is_automatically_primary(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Contactos Primeiro');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Empresa',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.suppliers.contacts.store', $supplier->id), [
                'name' => 'Primeiro Contacto',
                'is_primary' => 0,
            ])
            ->assertRedirect(route('admin.suppliers.edit', $supplier->id));

        $this->assertDatabaseHas('supplier_contacts', [
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'Primeiro Contacto',
            'is_primary' => true,
        ]);
    }

    public function test_store_without_is_primary_field_is_valid_and_keeps_single_primary(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Contactos Checkbox');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Empresa',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.suppliers.contacts.store', $supplier->id), [
                'name' => 'Contacto 1',
            ])
            ->assertRedirect(route('admin.suppliers.edit', $supplier->id));

        $this->actingAs($admin)
            ->post(route('admin.suppliers.contacts.store', $supplier->id), [
                'name' => 'Contacto 2',
            ])
            ->assertRedirect(route('admin.suppliers.edit', $supplier->id));

        $primaryCount = SupplierContact::query()
            ->where('company_id', $company->id)
            ->where('supplier_id', $supplier->id)
            ->where('is_primary', true)
            ->count();

        $this->assertSame(1, $primaryCount);
    }

    public function test_only_one_primary_contact_is_kept_per_supplier(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Contactos Primario Unico');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Empresa',
            'is_active' => true,
        ]);

        $primary = SupplierContact::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'Contacto A',
            'is_primary' => true,
        ]);

        $secondary = SupplierContact::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'Contacto B',
            'is_primary' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.suppliers.contacts.update', [$supplier->id, $secondary->id]), [
                'name' => 'Contacto B',
                'is_primary' => 1,
            ])
            ->assertRedirect(route('admin.suppliers.edit', $supplier->id));

        $primary->refresh();
        $secondary->refresh();

        $this->assertFalse($primary->is_primary);
        $this->assertTrue($secondary->is_primary);
    }

    public function test_deleting_primary_contact_promotes_another_contact(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Contactos Delete Primario');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor Empresa',
            'is_active' => true,
        ]);

        $primary = SupplierContact::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'Contacto Primario',
            'is_primary' => true,
        ]);

        $fallback = SupplierContact::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'Contacto Secundario',
            'is_primary' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.suppliers.contacts.destroy', [$supplier->id, $primary->id]))
            ->assertRedirect(route('admin.suppliers.edit', $supplier->id));

        $this->assertDatabaseMissing('supplier_contacts', ['id' => $primary->id]);
        $fallback->refresh();
        $this->assertTrue($fallback->is_primary);
    }

    public function test_user_without_permissions_cannot_manage_supplier_contacts(): void
    {
        $company = $this->createCompany('Empresa Fornecedores Contactos Sem Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => 'Fornecedor',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('admin.suppliers.contacts.store', $supplier->id), [
                'name' => 'Contacto',
            ])
            ->assertForbidden();
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }

    private function createCompanyUser(
        Company $company,
        string $role,
        bool $isActive = true,
        ?string $email = null
    ): User {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => $isActive,
            'email' => $email ?? Str::lower(Str::random(8)).'@example.test',
        ]);

        $user->syncRoles([$role]);

        return $user;
    }
}
