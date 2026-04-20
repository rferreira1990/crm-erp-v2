<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_dashboard_data_is_isolated(): void
    {
        $companyA = $this->createCompany('Empresa Dashboard A');
        $companyB = $this->createCompany('Empresa Dashboard B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $customerA = $this->createCustomer($companyA, 'Cliente A Dashboard');
        $customerB = $this->createCustomer($companyB, 'Cliente B Dashboard');

        $this->createDashboardQuote($companyA, [
            'customer' => $customerA,
            'status' => Quote::STATUS_DRAFT,
            'grand_total' => 100.00,
        ]);
        $this->createDashboardQuote($companyB, [
            'customer' => $customerB,
            'status' => Quote::STATUS_DRAFT,
            'grand_total' => 900.00,
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.quotes.dashboard'));
        $response->assertOk();
        $response->assertSee('Cliente A Dashboard');
        $response->assertDontSee('Cliente B Dashboard');
        $response->assertViewHas('kpis', function (array $kpis): bool {
            return (int) $kpis['total_quotes'] === 1
                && (int) ($kpis['counts'][Quote::STATUS_DRAFT] ?? 0) === 1
                && (float) ($kpis['values']['draft'] ?? 0) === 100.0;
        });
    }

    public function test_user_without_quotes_permission_cannot_access_dashboard(): void
    {
        $company = $this->createCompany('Empresa Dashboard Sem Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->actingAs($user)->get(route('admin.quotes.dashboard'))->assertForbidden();
    }

    public function test_dashboard_kpis_counts_and_values_are_correct(): void
    {
        $company = $this->createCompany('Empresa Dashboard KPI');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente KPI');

        $this->createDashboardQuote($company, ['customer' => $customer, 'status' => Quote::STATUS_DRAFT, 'grand_total' => 100.00]);
        $this->createDashboardQuote($company, ['customer' => $customer, 'status' => Quote::STATUS_SENT, 'grand_total' => 80.00]);
        $this->createDashboardQuote($company, ['customer' => $customer, 'status' => Quote::STATUS_VIEWED, 'grand_total' => 70.00]);
        $this->createDashboardQuote($company, ['customer' => $customer, 'status' => Quote::STATUS_APPROVED, 'grand_total' => 120.00]);
        $this->createDashboardQuote($company, ['customer' => $customer, 'status' => Quote::STATUS_APPROVED, 'grand_total' => 180.00]);
        $this->createDashboardQuote($company, ['customer' => $customer, 'status' => Quote::STATUS_REJECTED, 'grand_total' => 90.00]);
        $this->createDashboardQuote($company, ['customer' => $customer, 'status' => Quote::STATUS_CANCELLED, 'grand_total' => 30.00]);
        $this->createDashboardQuote($company, ['customer' => $customer, 'status' => Quote::STATUS_EXPIRED, 'grand_total' => 50.00]);

        $response = $this->actingAs($admin)->get(route('admin.quotes.dashboard'));
        $response->assertOk();
        $response->assertViewHas('kpis', function (array $kpis): bool {
            $checks = [
                (int) $kpis['total_quotes'] === 8,
                (int) ($kpis['counts'][Quote::STATUS_DRAFT] ?? 0) === 1,
                (int) ($kpis['counts'][Quote::STATUS_SENT] ?? 0) === 1,
                (int) ($kpis['counts'][Quote::STATUS_VIEWED] ?? 0) === 1,
                (int) ($kpis['counts'][Quote::STATUS_APPROVED] ?? 0) === 2,
                (int) ($kpis['counts'][Quote::STATUS_REJECTED] ?? 0) === 1,
                (int) ($kpis['counts'][Quote::STATUS_CANCELLED] ?? 0) === 1,
                (int) ($kpis['counts'][Quote::STATUS_EXPIRED] ?? 0) === 1,
                abs(((float) ($kpis['values']['draft'] ?? 0)) - 100.0) < 0.0001,
                abs(((float) ($kpis['values']['open'] ?? 0)) - 250.0) < 0.0001,
                abs(((float) ($kpis['values']['approved'] ?? 0)) - 300.0) < 0.0001,
                abs(((float) ($kpis['values']['lost'] ?? 0)) - 120.0) < 0.0001,
                abs(((float) ($kpis['values']['approved_avg_ticket'] ?? 0)) - 150.0) < 0.0001,
                abs(((float) ($kpis['approval_rate'] ?? 0)) - 40.0) < 0.0001,
            ];

            return ! in_array(false, $checks, true);
        });
    }

    public function test_dashboard_filters_for_period_status_customer_and_responsible_work(): void
    {
        $company = $this->createCompany('Empresa Dashboard Filtros');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customerA = $this->createCustomer($company, 'Cliente Filtro A');
        $customerB = $this->createCustomer($company, 'Cliente Filtro B');
        $responsibleA = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $responsibleB = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->createDashboardQuote($company, [
            'customer' => $customerA,
            'status' => Quote::STATUS_DRAFT,
            'assigned_user_id' => $responsibleA->id,
            'issue_date' => now()->subYear()->toDateString(),
            'grand_total' => 20.00,
        ]);

        $inPeriodDraft = $this->createDashboardQuote($company, [
            'customer' => $customerA,
            'status' => Quote::STATUS_DRAFT,
            'assigned_user_id' => $responsibleA->id,
            'issue_date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'grand_total' => 110.00,
        ]);

        $inPeriodApproved = $this->createDashboardQuote($company, [
            'customer' => $customerB,
            'status' => Quote::STATUS_APPROVED,
            'assigned_user_id' => $responsibleB->id,
            'issue_date' => now()->startOfMonth()->addDays(3)->toDateString(),
            'grand_total' => 220.00,
        ]);

        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo = now()->endOfMonth()->toDateString();

        $periodResponse = $this->actingAs($admin)->get(route('admin.quotes.dashboard', [
            'period' => 'custom',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]));
        $periodResponse->assertOk();
        $periodResponse->assertViewHas('kpis', fn (array $kpis): bool => (int) $kpis['total_quotes'] === 2);

        $statusResponse = $this->actingAs($admin)->get(route('admin.quotes.dashboard', [
            'period' => 'custom',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => Quote::STATUS_APPROVED,
        ]));
        $statusResponse->assertOk();
        $statusResponse->assertViewHas('kpis', fn (array $kpis): bool => (int) $kpis['total_quotes'] === 1);

        $customerResponse = $this->actingAs($admin)->get(route('admin.quotes.dashboard', [
            'period' => 'custom',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'customer_id' => $customerA->id,
        ]));
        $customerResponse->assertOk();
        $customerResponse->assertViewHas('recentQuotes', function (Collection $quotes) use ($inPeriodDraft): bool {
            return $quotes->count() === 1
                && (int) $quotes->first()->id === (int) $inPeriodDraft->id;
        });

        $responsibleResponse = $this->actingAs($admin)->get(route('admin.quotes.dashboard', [
            'period' => 'custom',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'assigned_user_id' => $responsibleB->id,
        ]));
        $responsibleResponse->assertOk();
        $responsibleResponse->assertViewHas('recentQuotes', function (Collection $quotes) use ($inPeriodApproved): bool {
            return $quotes->count() === 1
                && (int) $quotes->first()->id === (int) $inPeriodApproved->id;
        });
    }

    public function test_follow_ups_show_only_open_quotes_due_today_or_overdue(): void
    {
        $company = $this->createCompany('Empresa Dashboard Followup');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Followup');

        $overdue = $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_DRAFT,
            'follow_up_date' => now()->subDay()->toDateString(),
            'grand_total' => 100.00,
        ]);
        $today = $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_SENT,
            'follow_up_date' => now()->toDateString(),
            'grand_total' => 120.00,
        ]);
        $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_VIEWED,
            'follow_up_date' => now()->addDay()->toDateString(),
            'grand_total' => 140.00,
        ]);
        $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_APPROVED,
            'follow_up_date' => now()->subDay()->toDateString(),
            'grand_total' => 160.00,
        ]);
        $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_DRAFT,
            'follow_up_date' => null,
            'grand_total' => 80.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.quotes.dashboard'));
        $response->assertOk();
        $response->assertViewHas('followUpQuotes', function (Collection $quotes) use ($overdue, $today): bool {
            $quoteIds = $quotes->pluck('id')->sort()->values()->all();
            $expected = [(int) $overdue->id, (int) $today->id];
            sort($expected);

            return $quoteIds === $expected;
        });
    }

    public function test_responsible_performance_is_aggregated_by_assigned_user(): void
    {
        $company = $this->createCompany('Empresa Dashboard Responsavel');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Responsavel');
        $responsibleA = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $responsibleB = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_APPROVED,
            'assigned_user_id' => $responsibleA->id,
            'grand_total' => 100.00,
        ]);
        $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_REJECTED,
            'assigned_user_id' => $responsibleA->id,
            'grand_total' => 50.00,
        ]);
        $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_DRAFT,
            'assigned_user_id' => $responsibleB->id,
            'grand_total' => 80.00,
        ]);
        $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_APPROVED,
            'assigned_user_id' => $responsibleB->id,
            'grand_total' => 120.00,
        ]);
        $this->createDashboardQuote($company, [
            'customer' => $customer,
            'status' => Quote::STATUS_CANCELLED,
            'assigned_user_id' => $responsibleB->id,
            'grand_total' => 20.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.quotes.dashboard'));
        $response->assertOk();
        $response->assertViewHas('responsiblePerformance', function (Collection $rows) use ($responsibleA, $responsibleB): bool {
            $byResponsible = $rows->keyBy('assigned_user_id');
            $rowA = $byResponsible->get($responsibleA->id);
            $rowB = $byResponsible->get($responsibleB->id);

            if (! $rowA || ! $rowB) {
                return false;
            }

            $checks = [
                (int) $rowA['quotes_count'] === 2,
                abs(((float) $rowA['total_value']) - 150.0) < 0.0001,
                (int) $rowA['approved_count'] === 1,
                abs(((float) $rowA['approval_rate']) - 50.0) < 0.0001,
                (int) $rowB['quotes_count'] === 3,
                abs(((float) $rowB['total_value']) - 220.0) < 0.0001,
                (int) $rowB['approved_count'] === 1,
                abs(((float) $rowB['approval_rate']) - 50.0) < 0.0001,
            ];

            return ! in_array(false, $checks, true);
        });
    }

    public function test_filter_with_customer_from_another_company_returns_not_found(): void
    {
        $companyA = $this->createCompany('Empresa Dashboard 404 A');
        $companyB = $this->createCompany('Empresa Dashboard 404 B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $customerB = $this->createCustomer($companyB, 'Cliente Outra Empresa');

        $this->actingAs($adminA)->get(route('admin.quotes.dashboard', [
            'customer_id' => $customerB->id,
        ]))->assertNotFound();
    }

    private function createDashboardQuote(Company $company, array $attributes = []): Quote
    {
        $issueDate = Carbon::parse((string) ($attributes['issue_date'] ?? now()->toDateString()))->toDateString();
        $status = (string) ($attributes['status'] ?? Quote::STATUS_DRAFT);
        $grandTotal = (float) ($attributes['grand_total'] ?? 0);
        $customer = $attributes['customer'] ?? $this->createCustomer($company, 'Cliente '.Str::random(6));

        return Quote::createWithGeneratedNumber($company->id, [
            'version' => 1,
            'status' => $status,
            'customer_id' => $customer->id,
            'issue_date' => $issueDate,
            'valid_until' => $attributes['valid_until'] ?? null,
            'assigned_user_id' => $attributes['assigned_user_id'] ?? null,
            'follow_up_date' => $attributes['follow_up_date'] ?? null,
            'currency' => 'EUR',
            'is_active' => true,
            'is_locked' => in_array($status, Quote::closedCommercialStatuses(), true),
            'subtotal' => $grandTotal,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $grandTotal,
            'accepted_at' => $status === Quote::STATUS_APPROVED ? now() : null,
            'rejected_at' => $status === Quote::STATUS_REJECTED ? now() : null,
            'sent_at' => in_array($status, [Quote::STATUS_SENT, Quote::STATUS_VIEWED], true) ? now() : null,
            'last_sent_at' => in_array($status, [Quote::STATUS_SENT, Quote::STATUS_VIEWED], true) ? now() : null,
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

