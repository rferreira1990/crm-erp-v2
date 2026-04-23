<?php

namespace Tests\Feature\Admin;

use App\Models\ConstructionSite;
use App\Models\ConstructionSiteLog;
use App\Models\ConstructionSiteLogFile;
use App\Models\ConstructionSiteLogImage;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionSiteLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_isolation_blocks_cross_company_log_access(): void
    {
        $companyA = $this->createCompany('Empresa Obras Log A');
        $companyB = $this->createCompany('Empresa Obras Log B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $customerA = $this->createCustomer($companyA, 'Cliente A');
        $customerB = $this->createCustomer($companyB, 'Cliente B');

        $siteA = $this->createSite($companyA, $customerA, $adminA, 'Obra A');
        $siteB = $this->createSite($companyB, $customerB, $adminB, 'Obra B');
        $logB = $this->createLog($companyB, $siteB, $adminB, 'Registo B');

        $this->actingAs($adminA)->get(route('admin.construction-sites.logs.index', $siteB->id))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.construction-sites.logs.create', $siteB->id))->assertNotFound();
        $this->actingAs($adminA)->post(route('admin.construction-sites.logs.store', $siteB->id), [
            'log_date' => now()->toDateString(),
            'type' => ConstructionSiteLog::TYPE_NOTE,
            'title' => 'Tentativa',
            'description' => 'Descricao valida para teste.',
            'is_important' => 0,
        ])->assertNotFound();

        $this->actingAs($adminA)->get(route('admin.construction-sites.logs.show', [$siteB->id, $logB->id]))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.construction-sites.logs.edit', [$siteB->id, $logB->id]))->assertNotFound();
        $this->actingAs($adminA)->patch(route('admin.construction-sites.logs.update', [$siteB->id, $logB->id]), [
            'log_date' => now()->toDateString(),
            'type' => ConstructionSiteLog::TYPE_PROGRESS,
            'title' => 'Update',
            'description' => 'Descricao valida para update.',
            'is_important' => 1,
        ])->assertNotFound();
        $this->actingAs($adminA)->delete(route('admin.construction-sites.logs.destroy', [$siteB->id, $logB->id]))->assertNotFound();

        $this->actingAs($adminA)
            ->get(route('admin.construction-sites.logs.index', $siteA->id))
            ->assertOk();
    }

    public function test_user_without_permissions_cannot_manage_construction_site_logs_module(): void
    {
        $company = $this->createCompany('Empresa Obras Log Sem Perm');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Log Perm');
        $site = $this->createSite($company, $customer, $admin, 'Obra Perm');
        $log = $this->createLog($company, $site, $admin, 'Registo Perm');

        $this->actingAs($user)->get(route('admin.construction-sites.logs.index', $site->id))->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.logs.create', $site->id))->assertForbidden();
        $this->actingAs($user)->post(route('admin.construction-sites.logs.store', $site->id), [
            'log_date' => now()->toDateString(),
            'type' => ConstructionSiteLog::TYPE_NOTE,
            'title' => 'Novo',
            'description' => 'Descricao valida para criar registo.',
            'is_important' => 0,
        ])->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.logs.show', [$site->id, $log->id]))->assertForbidden();
        $this->actingAs($user)->get(route('admin.construction-sites.logs.edit', [$site->id, $log->id]))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.construction-sites.logs.update', [$site->id, $log->id]), [
            'log_date' => now()->toDateString(),
            'type' => ConstructionSiteLog::TYPE_PROGRESS,
            'title' => 'Update',
            'description' => 'Descricao valida para update.',
            'is_important' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.construction-sites.logs.destroy', [$site->id, $log->id]))->assertForbidden();
    }

    public function test_company_admin_can_create_construction_site_log_successfully(): void
    {
        $company = $this->createCompany('Empresa Obras Log Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $assigned = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $customer = $this->createCustomer($company, 'Cliente Log Create');
        $site = $this->createSite($company, $customer, $admin, 'Obra Create');

        $response = $this->actingAs($admin)->post(route('admin.construction-sites.logs.store', $site->id), [
            'log_date' => now()->toDateString(),
            'type' => ConstructionSiteLog::TYPE_PROGRESS,
            'title' => 'Avanco de trabalhos',
            'description' => 'Foi concluida a fase de infraestruturas sem constrangimentos.',
            'is_important' => 1,
            'assigned_user_id' => $assigned->id,
        ]);

        $log = ConstructionSiteLog::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(route('admin.construction-sites.logs.show', [$site->id, $log->id]));
        $this->assertSame($site->id, (int) $log->construction_site_id);
        $this->assertSame($company->id, (int) $log->company_id);
        $this->assertSame($assigned->id, (int) $log->assigned_user_id);
        $this->assertTrue((bool) $log->is_important);
    }

    public function test_validation_enforces_type_date_title_description_and_assigned_user_rules(): void
    {
        $companyA = $this->createCompany('Empresa Obras Log Val A');
        $companyB = $this->createCompany('Empresa Obras Log Val B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $userB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $customerA = $this->createCustomer($companyA, 'Cliente Log Val');
        $siteA = $this->createSite($companyA, $customerA, $adminA, 'Obra Val');

        $response = $this->actingAs($adminA)
            ->from(route('admin.construction-sites.logs.create', $siteA->id))
            ->post(route('admin.construction-sites.logs.store', $siteA->id), [
                'type' => 'invalid_type',
                'title' => '',
                'description' => 'curta',
                'assigned_user_id' => $userB->id,
                'is_important' => 0,
            ]);

        $response->assertRedirect(route('admin.construction-sites.logs.create', $siteA->id));
        $response->assertSessionHasErrors([
            'type',
            'log_date',
            'title',
            'description',
            'assigned_user_id',
        ]);
    }

    public function test_log_uploads_can_be_added_and_removed_with_multi_tenant_protection(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Obras Log Upload A');
        $companyB = $this->createCompany('Empresa Obras Log Upload B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $customerA = $this->createCustomer($companyA, 'Cliente Upload');
        $siteA = $this->createSite($companyA, $customerA, $adminA, 'Obra Upload');

        $this->actingAs($adminA)->post(route('admin.construction-sites.logs.store', $siteA->id), [
            'log_date' => now()->toDateString(),
            'type' => ConstructionSiteLog::TYPE_VISIT,
            'title' => 'Visita tecnica',
            'description' => 'Foi realizada uma visita tecnica para validacao do terreno.',
            'is_important' => 0,
            'images' => [UploadedFile::fake()->image('log.jpg')],
            'documents' => [UploadedFile::fake()->create('relatorio.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $log = ConstructionSiteLog::query()
            ->forCompany((int) $companyA->id)
            ->latest('id')
            ->firstOrFail();

        $image = ConstructionSiteLogImage::query()
            ->where('company_id', $companyA->id)
            ->where('construction_site_log_id', $log->id)
            ->firstOrFail();
        $file = ConstructionSiteLogFile::query()
            ->where('company_id', $companyA->id)
            ->where('construction_site_log_id', $log->id)
            ->firstOrFail();

        Storage::disk('local')->assertExists($image->file_path);
        Storage::disk('local')->assertExists($file->file_path);

        $this->actingAs($adminB)
            ->delete(route('admin.construction-sites.logs.images.destroy', [$siteA->id, $log->id, $image->id]))
            ->assertNotFound();

        $this->actingAs($adminA)
            ->delete(route('admin.construction-sites.logs.images.destroy', [$siteA->id, $log->id, $image->id]))
            ->assertRedirect(route('admin.construction-sites.logs.edit', [$siteA->id, $log->id]));

        $this->assertDatabaseMissing('construction_site_log_images', ['id' => $image->id]);
        Storage::disk('local')->assertMissing($image->file_path);

        $this->actingAs($adminA)
            ->delete(route('admin.construction-sites.logs.files.destroy', [$siteA->id, $log->id, $file->id]))
            ->assertRedirect(route('admin.construction-sites.logs.edit', [$siteA->id, $log->id]));

        $this->assertDatabaseMissing('construction_site_log_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($file->file_path);
    }

    public function test_show_page_renders_log_data_and_related_media(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Obras Log Show');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Show');
        $site = $this->createSite($company, $customer, $admin, 'Obra Show');

        $this->actingAs($admin)->post(route('admin.construction-sites.logs.store', $site->id), [
            'log_date' => now()->toDateString(),
            'type' => ConstructionSiteLog::TYPE_INCIDENT,
            'title' => 'Incidente eletrico',
            'description' => 'Foi identificado um incidente eletrico e aplicada medida corretiva.',
            'is_important' => 1,
            'images' => [UploadedFile::fake()->image('incidente.jpg')],
            'documents' => [UploadedFile::fake()->create('incidente.pdf', 120, 'application/pdf')],
        ])->assertRedirect();

        $log = ConstructionSiteLog::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('admin.construction-sites.logs.show', [$site->id, $log->id]));

        $response->assertOk();
        $response->assertSee('Incidente eletrico');
        $response->assertSee('Incidente');
        $response->assertSee('Importante');
        $response->assertSee('incidente.jpg');
        $response->assertSee('incidente.pdf');
        $response->assertSee($site->code);
    }

    public function test_construction_site_show_displays_recent_logs_and_all_logs_listing(): void
    {
        $company = $this->createCompany('Empresa Obras Log Timeline');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Timeline');
        $site = $this->createSite($company, $customer, $admin, 'Obra Timeline');

        $titles = [
            'Registo 1',
            'Registo 2',
            'Registo 3',
            'Registo 4',
            'Registo 5',
            'Registo 6',
        ];

        foreach ($titles as $index => $title) {
            $this->createLog(
                company: $company,
                site: $site,
                creator: $admin,
                title: $title,
                date: now()->subDays(5 - $index)->toDateString()
            );
        }

        $siteShow = $this->actingAs($admin)->get(route('admin.construction-sites.show', $site->id));
        $siteShow->assertOk();
        $siteShow->assertSee('Diario de obra');
        $siteShow->assertSee('Ver todos os logs');
        $siteShow->assertSee('Registo 6');
        $siteShow->assertDontSee('Registo 1');

        $logsIndex = $this->actingAs($admin)->get(route('admin.construction-sites.logs.index', $site->id));
        $logsIndex->assertOk();
        $logsIndex->assertSee('Registo 1');
        $logsIndex->assertSee('Registo 6');
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

    private function createLog(
        Company $company,
        ConstructionSite $site,
        User $creator,
        string $title,
        ?string $date = null
    ): ConstructionSiteLog {
        return ConstructionSiteLog::query()->create([
            'company_id' => $company->id,
            'construction_site_id' => $site->id,
            'log_date' => $date ?? now()->toDateString(),
            'type' => ConstructionSiteLog::TYPE_NOTE,
            'title' => $title,
            'description' => 'Descricao detalhada do registo de diario para contexto operativo.',
            'is_important' => false,
            'created_by' => $creator->id,
            'assigned_user_id' => null,
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
