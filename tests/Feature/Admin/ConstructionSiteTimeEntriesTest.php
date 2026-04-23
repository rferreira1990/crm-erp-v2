<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteTimeEntry;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionSiteTimeEntriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_isolation_blocks_cross_company_access(): void
    {
        $companyA = $this->createCompany('Empresa Horas A');
        $companyB = $this->createCompany('Empresa Horas B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $workerA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_USER);

        $workerB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_USER);
        $siteA = $this->createSite($companyA, $this->createCustomer($companyA, 'Cliente A'), $adminA, 'Obra A');
        $siteB = $this->createSite($companyB, $this->createCustomer($companyB, 'Cliente B'), $adminB, 'Obra B');
        $entryB = $this->createTimeEntry($companyB, $siteB, $workerB, $adminB);

        $this->actingAs($adminA)
            ->get(route('admin.construction-sites.time-entries.show', [$siteB->id, $entryB->id]))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->get(route('admin.construction-sites.time-entries.edit', [$siteB->id, $entryB->id]))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->patch(route('admin.construction-sites.time-entries.update', [$siteB->id, $entryB->id]), $this->entryPayload($workerA->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->delete(route('admin.construction-sites.time-entries.destroy', [$siteB->id, $entryB->id]))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->post(route('admin.construction-sites.time-entries.store', $siteB->id), $this->entryPayload($workerA->id))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->get(route('admin.construction-site-time-entries.index', ['construction_site_id' => $siteA->id]))
            ->assertOk();
    }

    public function test_create_time_entry_successfully(): void
    {
        $company = $this->createCompany('Empresa Horas Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $worker = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Horas'), $admin, 'Obra Horas');

        $this->actingAs($admin)
            ->post(route('admin.construction-sites.time-entries.store', $site->id), $this->entryPayload($worker->id))
            ->assertRedirect();

        $entry = ConstructionSiteTimeEntry::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame((int) $site->id, (int) $entry->construction_site_id);
        $this->assertSame((int) $worker->id, (int) $entry->user_id);
        $this->assertSame(8.0, (float) $entry->hours);
    }

    public function test_validation_fails_for_invalid_hours_cross_company_worker_and_missing_date(): void
    {
        $companyA = $this->createCompany('Empresa Horas Val A');
        $companyB = $this->createCompany('Empresa Horas Val B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $workerB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_USER);
        $site = $this->createSite($companyA, $this->createCustomer($companyA, 'Cliente Val'), $adminA, 'Obra Val');

        $this->actingAs($adminA)
            ->from(route('admin.construction-sites.time-entries.create', $site->id))
            ->post(route('admin.construction-sites.time-entries.store', $site->id), [
                'user_id' => $workerB->id,
                'work_date' => '',
                'hours' => 0,
                'description' => '',
                'task_type' => 'invalid',
            ])
            ->assertRedirect(route('admin.construction-sites.time-entries.create', $site->id))
            ->assertSessionHasErrors(['user_id', 'work_date', 'hours', 'description', 'task_type']);
    }

    public function test_total_cost_is_calculated_as_hours_times_hourly_cost(): void
    {
        $company = $this->createCompany('Empresa Horas Calculo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $worker = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Calculo'), $admin, 'Obra Calculo');

        $worker->forceFill(['hourly_cost' => 17.5])->save();

        $this->actingAs($admin)
            ->post(route('admin.construction-sites.time-entries.store', $site->id), $this->entryPayload($worker->id, 2.5))
            ->assertRedirect();

        $entry = ConstructionSiteTimeEntry::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(17.5, (float) $entry->hourly_cost);
        $this->assertSame(43.75, (float) $entry->total_cost);
    }

    public function test_edit_updates_entry_and_recalculates_cost(): void
    {
        $company = $this->createCompany('Empresa Horas Edit');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $worker = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Edit'), $admin, 'Obra Edit');
        $entry = $this->createTimeEntry($company, $site, $worker, $admin);

        $this->actingAs($admin)
            ->patch(route('admin.construction-sites.time-entries.update', [$site->id, $entry->id]), $this->entryPayload($worker->id, 5.5, 'Atualizado'))
            ->assertRedirect(route('admin.construction-sites.time-entries.show', [$site->id, $entry->id]));

        $entry->refresh();
        $this->assertSame(5.5, (float) $entry->hours);
        $this->assertSame('Atualizado', $entry->description);
        $this->assertSame(0.0, (float) $entry->hourly_cost);
        $this->assertSame(0.0, (float) $entry->total_cost);
    }

    public function test_delete_removes_entry_with_permission(): void
    {
        $company = $this->createCompany('Empresa Horas Delete');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $worker = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Delete'), $admin, 'Obra Delete');
        $entry = $this->createTimeEntry($company, $site, $worker, $admin);

        $this->actingAs($admin)
            ->delete(route('admin.construction-sites.time-entries.destroy', [$site->id, $entry->id]))
            ->assertRedirect(route('admin.construction-site-time-entries.index', ['construction_site_id' => $site->id]));

        $this->assertDatabaseMissing('construction_site_time_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_site_show_displays_time_summary_and_recent_entries(): void
    {
        $company = $this->createCompany('Empresa Horas Show');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $worker = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $site = $this->createSite($company, $this->createCustomer($company, 'Cliente Show'), $admin, 'Obra Show');

        $entry = $this->createTimeEntry($company, $site, $worker, $admin, 3.5, 'Instalacao quadro');

        $response = $this->actingAs($admin)->get(route('admin.construction-sites.show', $site->id));
        $response->assertOk();
        $response->assertSee('Mao de obra');
        $response->assertSee('Total de horas');
        $response->assertSee('Instalacao quadro');
        $response->assertSee((string) $entry->worker->name);
    }

    private function entryPayload(int $userId, float $hours = 8.0, string $description = 'Trabalho tecnico'): array
    {
        return [
            'user_id' => $userId,
            'work_date' => now()->toDateString(),
            'hours' => $hours,
            'description' => $description,
            'task_type' => ConstructionSiteTimeEntry::TASK_INSTALLATION,
        ];
    }

    private function createTimeEntry(
        Company $company,
        ConstructionSite $site,
        User $worker,
        User $creator,
        float $hours = 8.0,
        string $description = 'Lancamento inicial'
    ): ConstructionSiteTimeEntry {
        return ConstructionSiteTimeEntry::query()->create([
            'company_id' => $company->id,
            'construction_site_id' => $site->id,
            'user_id' => $worker->id,
            'work_date' => now()->toDateString(),
            'hours' => round($hours, 2),
            'hourly_cost' => 0,
            'total_cost' => 0,
            'description' => $description,
            'task_type' => ConstructionSiteTimeEntry::TASK_INSTALLATION,
            'created_by' => $creator->id,
        ]);
    }

    private function createSite(Company $company, Customer $customer, User $creator, string $name): ConstructionSite
    {
        return ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => $name,
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_DRAFT,
            'created_by' => $creator->id,
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
