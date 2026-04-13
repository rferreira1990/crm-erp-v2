<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerContactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_access_is_blocked_with_not_found(): void
    {
        $companyA = $this->createCompany('Empresa Contactos A');
        $companyB = $this->createCompany('Empresa Contactos B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $customerB = Customer::query()->create([
            'company_id' => $companyB->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente B',
            'is_active' => true,
        ]);

        $contactB = CustomerContact::query()->create([
            'company_id' => $companyB->id,
            'customer_id' => $customerB->id,
            'name' => 'Contacto B',
            'is_primary' => true,
        ]);

        $this->actingAs($adminA)
            ->get(route('admin.customers.contacts.create', $customerB->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.customers.contacts.store', $customerB->id), [
                'name' => 'Novo contacto',
                'is_primary' => 1,
            ])
            ->assertNotFound();

        $this->actingAs($adminA)
            ->get(route('admin.customers.contacts.edit', [$customerB->id, $contactB->id]))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->delete(route('admin.customers.contacts.destroy', [$customerB->id, $contactB->id]))
            ->assertNotFound();
    }

    public function test_only_company_customers_can_create_and_edit_contacts(): void
    {
        $company = $this->createCompany('Empresa Contactos Tipo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $individualCustomer = Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_INDIVIDUAL,
            'name' => 'Cliente Individual',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.contacts.create', $individualCustomer->id))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.customers.contacts.store', $individualCustomer->id), [
                'name' => 'Contacto Inválido',
                'is_primary' => 1,
            ])
            ->assertNotFound();

        $contact = CustomerContact::query()->create([
            'company_id' => $company->id,
            'customer_id' => $individualCustomer->id,
            'name' => 'Contacto Antigo',
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.contacts.edit', [$individualCustomer->id, $contact->id]))
            ->assertNotFound();
    }

    public function test_first_contact_is_automatically_primary(): void
    {
        $company = $this->createCompany('Empresa Contactos Primeiro');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Empresa',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.customers.contacts.store', $customer->id), [
                'name' => 'Primeiro Contacto',
                'is_primary' => 0,
            ])
            ->assertRedirect(route('admin.customers.edit', $customer->id));

        $this->assertDatabaseHas('customer_contacts', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => 'Primeiro Contacto',
            'is_primary' => true,
        ]);
    }

    public function test_only_one_primary_contact_is_kept_per_customer(): void
    {
        $company = $this->createCompany('Empresa Contactos Primario Unico');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Empresa',
            'is_active' => true,
        ]);

        $primary = CustomerContact::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => 'Contacto A',
            'is_primary' => true,
        ]);

        $secondary = CustomerContact::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => 'Contacto B',
            'is_primary' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.customers.contacts.update', [$customer->id, $secondary->id]), [
                'name' => 'Contacto B',
                'is_primary' => 1,
            ])
            ->assertRedirect(route('admin.customers.edit', $customer->id));

        $primary->refresh();
        $secondary->refresh();

        $this->assertFalse($primary->is_primary);
        $this->assertTrue($secondary->is_primary);
    }

    public function test_deleting_primary_contact_promotes_another_contact(): void
    {
        $company = $this->createCompany('Empresa Contactos Delete Primario');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => 'Cliente Empresa',
            'is_active' => true,
        ]);

        $primary = CustomerContact::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => 'Contacto Primario',
            'is_primary' => true,
        ]);

        $fallback = CustomerContact::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => 'Contacto Secundario',
            'is_primary' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.customers.contacts.destroy', [$customer->id, $primary->id]))
            ->assertRedirect(route('admin.customers.edit', $customer->id));

        $this->assertDatabaseMissing('customer_contacts', ['id' => $primary->id]);
        $fallback->refresh();
        $this->assertTrue($fallback->is_primary);
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
