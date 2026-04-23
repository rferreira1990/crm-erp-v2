<?php

namespace Tests\Feature\Admin;

use App\Models\ConstructionSite;
use App\Models\ConstructionSiteFile;
use App\Models\ConstructionSiteImage;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Quote;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionSitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_isolation_blocks_cross_company_access(): void
    {
        $companyA = $this->createCompany('Empresa Obras A');
        $companyB = $this->createCompany('Empresa Obras B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $customerA = $this->createCustomer($companyA, 'Cliente Obras A');
        $customerB = $this->createCustomer($companyB, 'Cliente Obras B');

        $siteA = ConstructionSite::createWithGeneratedCode((int) $companyA->id, [
            'name' => 'Obra Alfa',
            'customer_id' => $customerA->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'created_by' => $adminA->id,
            'is_active' => true,
        ]);

        $siteB = ConstructionSite::createWithGeneratedCode((int) $companyB->id, [
            'name' => 'Obra Beta',
            'customer_id' => $customerB->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'created_by' => $adminB->id,
            'is_active' => true,
        ]);

        $this->actingAs($adminA)
            ->get(route('admin.construction-sites.index'))
            ->assertOk()
            ->assertSee('Obra Alfa')
            ->assertDontSee('Obra Beta');

        $this->actingAs($adminA)->get(route('admin.construction-sites.show', $siteB->id))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.construction-sites.edit', $siteB->id))->assertNotFound();
        $this->actingAs($adminA)->patch(route('admin.construction-sites.update', $siteB->id), [
            'name' => 'Novo',
            'customer_id' => $customerA->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'is_active' => 1,
        ])->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.construction-sites.destroy', $siteB->id))->assertNotFound();
    }

    public function test_user_without_permissions_cannot_manage_construction_sites_module(): void
    {
        $company = $this->createCompany('Empresa Obras Sem Perm');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Obra Perm');

        $site = ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra Bloqueada',
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.construction-sites.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.construction-sites.store'), [
            'name' => 'Nova',
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.show', $site->id))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.construction-sites.update', $site->id), [
            'name' => 'Editada',
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.construction-sites.destroy', $site->id))->assertForbidden();
    }

    public function test_company_admin_can_create_construction_site_with_auto_code_and_valid_links(): void
    {
        $company = $this->createCompany('Empresa Obras Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $assigned = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Obra Create');
        $contact = $this->createCustomerContact($company, $customer, 'Contacto Obra Create');
        $quote = $this->createQuote($company, $customer, $contact, Quote::STATUS_APPROVED);

        $response = $this->actingAs($admin)->post(route('admin.construction-sites.store'), [
            'name' => 'Obra Nova',
            'customer_id' => $customer->id,
            'customer_contact_id' => $contact->id,
            'quote_id' => $quote->id,
            'address' => 'Rua da Obra, 10',
            'postal_code' => '1000-123',
            'locality' => 'Lisboa',
            'city' => 'Lisboa',
            'assigned_user_id' => $assigned->id,
            'status' => ConstructionSite::STATUS_PLANNED,
            'planned_start_date' => now()->toDateString(),
            'planned_end_date' => now()->addDays(10)->toDateString(),
            'description' => 'Descricao da obra',
            'internal_notes' => 'Notas internas',
            'is_active' => 1,
        ]);

        $site = ConstructionSite::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(route('admin.construction-sites.show', $site->id));
        $this->assertMatchesRegularExpression('/^OBR-\d{4}-\d{4}$/', $site->code);
        $this->assertSame($customer->id, (int) $site->customer_id);
        $this->assertSame($contact->id, (int) $site->customer_contact_id);
        $this->assertSame($quote->id, (int) $site->quote_id);
        $this->assertSame($assigned->id, (int) $site->assigned_user_id);
    }

    public function test_create_validation_enforces_contact_quote_assigned_user_and_postal_code_rules(): void
    {
        $companyA = $this->createCompany('Empresa Obras Val A');
        $companyB = $this->createCompany('Empresa Obras Val B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $userB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $customerA = $this->createCustomer($companyA, 'Cliente A');
        $customerA2 = $this->createCustomer($companyA, 'Cliente A2');
        $contactFromOtherCustomer = $this->createCustomerContact($companyA, $customerA2, 'Contacto Outro Cliente');
        $draftQuote = $this->createQuote($companyA, $customerA, null, Quote::STATUS_DRAFT);

        $response = $this->actingAs($adminA)
            ->from(route('admin.construction-sites.create'))
            ->post(route('admin.construction-sites.store'), [
                'name' => 'Obra invalida',
                'customer_id' => $customerA->id,
                'customer_contact_id' => $contactFromOtherCustomer->id,
                'quote_id' => $draftQuote->id,
                'assigned_user_id' => $userB->id,
                'postal_code' => '1000-12',
                'status' => ConstructionSite::STATUS_DRAFT,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('admin.construction-sites.create'));
        $response->assertSessionHasErrors([
            'customer_contact_id',
            'quote_id',
            'assigned_user_id',
            'postal_code',
        ]);
    }

    public function test_uploads_can_be_added_and_removed_with_multi_tenant_protection(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Obras Upload A');
        $companyB = $this->createCompany('Empresa Obras Upload B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $customerA = $this->createCustomer($companyA, 'Cliente Upload');

        $this->actingAs($adminA)->post(route('admin.construction-sites.store'), [
            'name' => 'Obra Upload',
            'customer_id' => $customerA->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'is_active' => 1,
            'images' => [UploadedFile::fake()->image('obra.jpg')],
            'documents' => [UploadedFile::fake()->create('planta.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $site = ConstructionSite::query()
            ->forCompany((int) $companyA->id)
            ->latest('id')
            ->firstOrFail();

        $image = ConstructionSiteImage::query()
            ->where('company_id', $companyA->id)
            ->where('construction_site_id', $site->id)
            ->firstOrFail();
        $file = ConstructionSiteFile::query()
            ->where('company_id', $companyA->id)
            ->where('construction_site_id', $site->id)
            ->firstOrFail();

        Storage::disk('local')->assertExists($image->file_path);
        Storage::disk('local')->assertExists($file->file_path);

        $this->actingAs($adminB)
            ->delete(route('admin.construction-sites.images.destroy', [$site->id, $image->id]))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->delete(route('admin.construction-sites.images.destroy', [$site->id, $image->id]))
            ->assertRedirect(route('admin.construction-sites.edit', $site->id));

        $this->assertDatabaseMissing('construction_site_images', ['id' => $image->id]);
        Storage::disk('local')->assertMissing($image->file_path);

        $this->actingAs($adminA)
            ->delete(route('admin.construction-sites.files.destroy', [$site->id, $file->id]))
            ->assertRedirect(route('admin.construction-sites.edit', $site->id));

        $this->assertDatabaseMissing('construction_site_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($file->file_path);
    }

    public function test_show_page_renders_main_data_and_relations(): void
    {
        $company = $this->createCompany('Empresa Obras Show');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $assigned = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Show');
        $contact = $this->createCustomerContact($company, $customer, 'Contacto Show');
        $quote = $this->createQuote($company, $customer, $contact, Quote::STATUS_APPROVED);

        $site = ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => 'Obra Show',
            'customer_id' => $customer->id,
            'customer_contact_id' => $contact->id,
            'quote_id' => $quote->id,
            'assigned_user_id' => $assigned->id,
            'status' => ConstructionSite::STATUS_PLANNED,
            'address' => 'Rua Show, 1',
            'postal_code' => '4000-111',
            'locality' => 'Porto',
            'city' => 'Porto',
            'description' => 'Descricao show',
            'internal_notes' => 'Notas show',
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.construction-sites.show', $site->id));

        $response->assertOk();
        $response->assertSee($site->code);
        $response->assertSee('Obra Show');
        $response->assertSee('Cliente Show');
        $response->assertSee('Contacto Show');
        $response->assertSee($quote->number);
        $response->assertSee('Rua Show, 1');
        $response->assertSee('Porto');
        $response->assertSee('Planeada');
    }

    private function createQuote(
        Company $company,
        Customer $customer,
        ?CustomerContact $contact = null,
        string $status = Quote::STATUS_DRAFT
    ): Quote {
        return Quote::createWithGeneratedNumber((int) $company->id, [
            'status' => $status,
            'customer_id' => $customer->id,
            'customer_contact_id' => $contact?->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'customer_mobile' => $customer->mobile,
            'customer_address' => $customer->address,
            'customer_postal_code' => $customer->postal_code,
            'customer_locality' => $customer->locality,
            'customer_city' => $customer->city,
            'customer_contact_name' => $contact?->name,
            'customer_contact_email' => $contact?->email,
            'customer_contact_phone' => $contact?->phone,
            'customer_contact_job_title' => $contact?->job_title,
            'is_active' => true,
        ]);
    }

    private function createCustomer(Company $company, string $name): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createCustomerContact(Company $company, Customer $customer, string $name): CustomerContact
    {
        return CustomerContact::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::lower(Str::random(4)).'@example.test',
            'is_primary' => true,
        ]);
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }

    private function createCompanyUser(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => true,
            'email' => Str::lower(Str::random(8)).'@example.test',
        ]);

        $user->syncRoles([$role]);

        return $user;
    }
}
