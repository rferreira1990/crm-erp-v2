<?php

namespace Tests\Feature\Admin;

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\CompanyCalendarIntegration;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_can_open_calendar_integrations_page(): void
    {
        $company = $this->createCompany('Empresa CalDAV 1');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.calendar.integrations.index'))
            ->assertOk()
            ->assertSee('Integracao CalDAV');
    }

    public function test_user_without_permission_cannot_open_calendar_integrations_page(): void
    {
        $company = $this->createCompany('Empresa CalDAV 2');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->actingAs($user)
            ->get(route('admin.calendar.integrations.index'))
            ->assertForbidden();
    }

    public function test_store_integration_with_company_scope_and_encrypted_password(): void
    {
        $company = $this->createCompany('Empresa CalDAV 3');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)
            ->put(route('admin.calendar.integrations.update'), [
                'provider' => 'caldav',
                'name' => 'Calendario Empresa',
                'username' => 'ricardo@fortiscasa.pt',
                'password' => '123456',
                'base_url' => 'https://mail.fortiscasa.pt:2080',
                'calendar_url' => 'https://mail.fortiscasa.pt:2080/calendars/ricardo@fortiscasa.pt/calendar',
                'is_active' => '1',
                'sync_enabled' => '1',
                'user_id' => '',
            ])
            ->assertRedirect(route('admin.calendar.integrations.index'));

        $integration = CompanyCalendarIntegration::query()
            ->forCompany((int) $company->id)
            ->firstOrFail();

        $this->assertSame((int) $company->id, (int) $integration->company_id);
        $this->assertNotSame('123456', (string) $integration->getRawOriginal('password'));
        $this->assertSame('123456', (string) $integration->password);
    }

    public function test_update_without_password_keeps_existing_password(): void
    {
        $company = $this->createCompany('Empresa CalDAV 4');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $integration = CompanyCalendarIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyCalendarIntegration::PROVIDER_CALDAV,
            'name' => 'Inicial',
            'username' => 'user@test.pt',
            'password' => 'secret-1',
            'base_url' => 'https://mail.fortiscasa.pt:2080',
            'calendar_url' => 'https://mail.fortiscasa.pt:2080/calendars/user/calendar',
            'is_active' => true,
            'sync_enabled' => true,
        ]);

        $previousRaw = (string) $integration->getRawOriginal('password');

        $this->actingAs($admin)
            ->put(route('admin.calendar.integrations.update'), [
                'provider' => 'caldav',
                'name' => 'Atualizada',
                'username' => 'user@test.pt',
                'password' => '',
                'base_url' => 'https://mail.fortiscasa.pt:2080',
                'calendar_url' => 'https://mail.fortiscasa.pt:2080/calendars/user/calendar',
                'is_active' => '1',
                'sync_enabled' => '1',
                'user_id' => '',
            ])
            ->assertRedirect(route('admin.calendar.integrations.index'));

        $integration->refresh();
        $this->assertSame($previousRaw, (string) $integration->getRawOriginal('password'));
    }

    public function test_test_connection_uses_propfind_and_handles_success(): void
    {
        Http::fake([
            'https://mail.fortiscasa.pt:2080/*' => Http::response('', 207),
        ]);

        $company = $this->createCompany('Empresa CalDAV 5');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        CompanyCalendarIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyCalendarIntegration::PROVIDER_CALDAV,
            'name' => 'Calendario',
            'username' => 'user@test.pt',
            'password' => 'secret-1',
            'base_url' => 'https://mail.fortiscasa.pt:2080',
            'calendar_url' => 'https://mail.fortiscasa.pt:2080/calendars/user/calendar',
            'is_active' => true,
            'sync_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.calendar.integrations.test-connection'))
            ->assertRedirect(route('admin.calendar.integrations.index'))
            ->assertSessionHas('status');
    }

    public function test_event_creation_syncs_using_same_company_integration(): void
    {
        Http::fake([
            'https://mail.fortiscasa.pt:2080/*' => Http::response('', 201, ['ETag' => '"abc123"']),
        ]);

        $company = $this->createCompany('Empresa CalDAV 6');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        CompanyCalendarIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyCalendarIntegration::PROVIDER_CALDAV,
            'name' => 'Calendario',
            'username' => 'user@test.pt',
            'password' => 'secret-1',
            'base_url' => 'https://mail.fortiscasa.pt:2080',
            'calendar_url' => 'https://mail.fortiscasa.pt:2080/calendars/user/calendar',
            'is_active' => true,
            'sync_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Evento sync',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDay()->setHour(9)->toDateTimeString(),
                'ends_at' => now()->addDay()->setHour(10)->toDateTimeString(),
                'all_day' => false,
            ])
            ->assertCreated();

        $event = CalendarEvent::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->assertDatabaseHas('calendar_event_external_syncs', [
            'company_id' => $company->id,
            'calendar_event_id' => $event->id,
            'sync_status' => 'synced',
        ]);
    }

    public function test_event_does_not_use_integration_from_other_company(): void
    {
        Http::fake([
            '*' => Http::response('', 201),
        ]);

        $companyA = $this->createCompany('Empresa CalDAV 7A');
        $companyB = $this->createCompany('Empresa CalDAV 7B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        CompanyCalendarIntegration::query()->create([
            'company_id' => $companyB->id,
            'provider' => CompanyCalendarIntegration::PROVIDER_CALDAV,
            'name' => 'Calendario B',
            'username' => 'b@test.pt',
            'password' => 'secret-b',
            'base_url' => 'https://mail.fortiscasa.pt:2080',
            'calendar_url' => 'https://mail.fortiscasa.pt:2080/calendars/b/calendar',
            'is_active' => true,
            'sync_enabled' => true,
        ]);

        $this->actingAs($adminA)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Evento A',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDay()->setHour(9)->toDateTimeString(),
                'ends_at' => now()->addDay()->setHour(10)->toDateTimeString(),
                'all_day' => false,
            ])
            ->assertCreated();

        $eventA = CalendarEvent::query()->forCompany((int) $companyA->id)->latest('id')->firstOrFail();
        $this->assertDatabaseMissing('calendar_event_external_syncs', [
            'company_id' => $companyA->id,
            'calendar_event_id' => $eventA->id,
        ]);
    }

    public function test_caldav_error_does_not_block_erp_event_creation(): void
    {
        Http::fake([
            'https://mail.fortiscasa.pt:2080/*' => Http::response('', 500),
        ]);

        $company = $this->createCompany('Empresa CalDAV 8');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        CompanyCalendarIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyCalendarIntegration::PROVIDER_CALDAV,
            'name' => 'Calendario',
            'username' => 'user@test.pt',
            'password' => 'secret-1',
            'base_url' => 'https://mail.fortiscasa.pt:2080',
            'calendar_url' => 'https://mail.fortiscasa.pt:2080/calendars/user/calendar',
            'is_active' => true,
            'sync_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Evento continua',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDay()->setHour(9)->toDateTimeString(),
                'ends_at' => now()->addDay()->setHour(10)->toDateTimeString(),
                'all_day' => false,
            ])
            ->assertCreated();

        $event = CalendarEvent::query()->forCompany((int) $company->id)->latest('id')->firstOrFail();

        $this->assertDatabaseHas('calendar_event_external_syncs', [
            'company_id' => $company->id,
            'calendar_event_id' => $event->id,
            'sync_status' => 'failed',
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
