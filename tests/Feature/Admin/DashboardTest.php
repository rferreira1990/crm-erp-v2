<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_dashboard_loads_for_company_admin(): void
    {
        $company = $this->createCompany('Empresa Dashboard A');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Total vendido no mes');
        $response->assertSee('Orcamentos por estado');
        $response->assertSee('Alertas operacionais');
    }

    public function test_dashboard_respects_company_scope_and_hides_other_company_data(): void
    {
        $companyA = $this->createCompany('Empresa Dashboard Scope A');
        $companyB = $this->createCompany('Empresa Dashboard Scope B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $customerA = $this->createCustomer($companyA, 'Cliente Scope A');
        $customerB = $this->createCustomer($companyB, 'Cliente Scope B');

        $this->createSalesDocument($companyA, $adminA, $customerA, 'DV-A-0001', now(), 100);
        $this->createSalesDocument($companyB, $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN), $customerB, 'DV-B-0001', now(), 999);

        $this->createQuote($companyA, $customerA, 'Q-A-0001', Quote::STATUS_DRAFT, now());
        $this->createQuote($companyB, $customerB, 'Q-B-0001', Quote::STATUS_DRAFT, now());

        $response = $this->actingAs($adminA)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('DV-A-0001');
        $response->assertSee('Q-A-0001');
        $response->assertDontSee('DV-B-0001');
        $response->assertDontSee('Q-B-0001');
    }

    public function test_dashboard_kpis_are_calculated_correctly(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 25, 10, 0, 0));

        $company = $this->createCompany('Empresa Dashboard KPI');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente KPI');

        $docA = $this->createSalesDocument($company, $admin, $customer, 'DV-KPI-0001', Carbon::now()->subDays(1), 1000, SalesDocument::PAYMENT_STATUS_UNPAID);
        $docB = $this->createSalesDocument($company, $admin, $customer, 'DV-KPI-0002', Carbon::now()->subDays(2), 500, SalesDocument::PAYMENT_STATUS_PARTIAL);

        SalesDocumentReceipt::query()->create([
            'company_id' => $company->id,
            'number' => 'REC-KPI-0001',
            'sales_document_id' => $docB->id,
            'customer_id' => $customer->id,
            'receipt_date' => Carbon::now()->toDateString(),
            'amount' => 200,
            'status' => SalesDocumentReceipt::STATUS_ISSUED,
            'issued_at' => Carbon::now(),
            'created_by' => $admin->id,
        ]);

        $this->createQuote($company, $customer, 'Q-KPI-OPEN-1', Quote::STATUS_DRAFT, Carbon::now()->toDateString());
        $this->createQuote($company, $customer, 'Q-KPI-OPEN-2', Quote::STATUS_SENT, Carbon::now()->toDateString());
        $this->createQuote($company, $customer, 'Q-KPI-DEC-1', Quote::STATUS_APPROVED, Carbon::now()->toDateString());
        $this->createQuote($company, $customer, 'Q-KPI-DEC-2', Quote::STATUS_REJECTED, Carbon::now()->toDateString());

        ConstructionSite::query()->create([
            'company_id' => $company->id,
            'code' => 'OBR-KPI-0001',
            'name' => 'Obra KPI',
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_IN_PROGRESS,
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', [
            'period' => 'this_year',
        ]));

        $response->assertOk();
        $response->assertSee('1.500,00 €'); // vendido no mes/ano
        $response->assertSee('1.300,00 €'); // valor em aberto (1500 - 200)
        $response->assertSee('200,00 €');   // recebido no mes
        $response->assertSee('50,00%');     // 1 aprovado / 2 decididos
        $response->assertSee('Obras ativas');
        $response->assertSee('DV-KPI-0001');
        $response->assertSee('Q-KPI-OPEN-1');
    }

    public function test_non_admin_user_does_not_see_sensitive_margin_information(): void
    {
        $company = $this->createCompany('Empresa Dashboard User');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Margem estimada das obras');
        $response->assertDontSee('Obras acima do orcamento');
    }

    public function test_dashboard_custom_period_filter_updates_recent_lists(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 25, 10, 0, 0));

        $company = $this->createCompany('Empresa Dashboard Filter');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Filter');

        $this->createSalesDocument($company, $admin, $customer, 'DV-FILTER-OLD', Carbon::create(2026, 2, 10), 400);
        $this->createSalesDocument($company, $admin, $customer, 'DV-FILTER-NEW', Carbon::create(2026, 4, 20), 600);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', [
            'period' => 'custom',
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
        ]));

        $response->assertOk();
        $response->assertSee('DV-FILTER-OLD');
        $response->assertDontSee('DV-FILTER-NEW');
    }

    public function test_dashboard_does_not_break_when_company_has_no_data(): void
    {
        $company = $this->createCompany('Empresa Dashboard Empty');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('0,00 €');
        $response->assertSee('Sem registos.');
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

    private function createCustomer(Company $company, string $name): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createQuote(Company $company, Customer $customer, string $number, string $status, Carbon|string $issueDate): Quote
    {
        return Quote::query()->create([
            'company_id' => $company->id,
            'number' => $number,
            'status' => $status,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'issue_date' => $issueDate,
            'grand_total' => 100,
            'currency' => 'EUR',
            'is_active' => true,
        ]);
    }

    private function createSalesDocument(
        Company $company,
        User $user,
        Customer $customer,
        string $number,
        Carbon|string $issueDate,
        float $grandTotal,
        string $paymentStatus = SalesDocument::PAYMENT_STATUS_UNPAID
    ): SalesDocument {
        return SalesDocument::query()->create([
            'company_id' => $company->id,
            'number' => $number,
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'status' => SalesDocument::STATUS_ISSUED,
            'payment_status' => $paymentStatus,
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'issue_date' => $issueDate,
            'grand_total' => $grandTotal,
            'currency' => 'EUR',
            'issued_at' => Carbon::parse((string) $issueDate)->startOfDay(),
            'created_by' => $user->id,
        ]);
    }
}

