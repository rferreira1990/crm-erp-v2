<?php

namespace Tests\Feature\Admin;

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_user_with_permission_can_open_calendar_page(): void
    {
        $company = $this->createCompany('Empresa Agenda 1');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $admin->forceFill(['calendar_color' => '#2c7be5'])->save();

        $this->actingAs($admin)
            ->get(route('admin.calendar.index'))
            ->assertOk()
            ->assertSee('Agenda e Tarefas')
            ->assertSee('Sem responsavel');
    }

    public function test_user_without_permission_cannot_open_calendar_page(): void
    {
        $company = $this->createCompany('Empresa Agenda 2');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->actingAs($user)
            ->get(route('admin.calendar.index'))
            ->assertForbidden();
    }

    public function test_creates_event_with_company_id_from_authenticated_user(): void
    {
        $company = $this->createCompany('Empresa Agenda 3');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Agenda');

        $this->actingAs($admin)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Reuniao cliente',
                'description' => 'Checklist de kickoff',
                'type' => CalendarEvent::TYPE_MEETING,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDay()->setHour(10)->setMinute(0)->toDateTimeString(),
                'ends_at' => now()->addDay()->setHour(11)->setMinute(0)->toDateTimeString(),
                'all_day' => false,
                'customer_id' => $customer->id,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Evento criado com sucesso.');

        $event = CalendarEvent::query()
            ->forCompany((int) $company->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame((int) $company->id, (int) $event->company_id);
        $this->assertSame((int) $admin->id, (int) $event->created_by);
        $this->assertSame('Reuniao cliente', (string) $event->title);
    }

    public function test_store_with_customer_from_other_company_returns_404(): void
    {
        $companyA = $this->createCompany('Empresa Agenda A');
        $companyB = $this->createCompany('Empresa Agenda B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $customerB = $this->createCustomer($companyB, 'Cliente B');

        $this->actingAs($adminA)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Evento invalido',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->toDateTimeString(),
                'all_day' => false,
                'customer_id' => $customerB->id,
            ])
            ->assertNotFound();
    }

    public function test_store_with_construction_site_from_other_company_returns_404(): void
    {
        $companyA = $this->createCompany('Empresa Agenda C');
        $companyB = $this->createCompany('Empresa Agenda D');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $siteB = $this->createConstructionSite($companyB, 'Obra B');

        $this->actingAs($adminA)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Evento obra invalida',
                'type' => CalendarEvent::TYPE_CONSTRUCTION_SITE,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_HIGH,
                'starts_at' => now()->toDateTimeString(),
                'all_day' => false,
                'construction_site_id' => $siteB->id,
            ])
            ->assertNotFound();
    }

    public function test_events_endpoint_lists_only_current_company_events(): void
    {
        $companyA = $this->createCompany('Empresa Agenda E');
        $companyB = $this->createCompany('Empresa Agenda F');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        CalendarEvent::query()->create([
            'company_id' => $companyA->id,
            'title' => 'Evento A',
            'type' => CalendarEvent::TYPE_TASK,
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => CalendarEvent::PRIORITY_NORMAL,
            'starts_at' => now()->addDay(),
            'all_day' => false,
        ]);

        CalendarEvent::query()->create([
            'company_id' => $companyB->id,
            'title' => 'Evento B',
            'type' => CalendarEvent::TYPE_TASK,
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => CalendarEvent::PRIORITY_NORMAL,
            'starts_at' => now()->addDay(),
            'all_day' => false,
        ]);

        $response = $this->actingAs($adminA)
            ->getJson(route('admin.calendar.events', [
                'start' => now()->startOfMonth()->toIso8601String(),
                'end' => now()->endOfMonth()->toIso8601String(),
            ]))
            ->assertOk();

        $payload = $response->json();
        $titles = collect($payload)->pluck('title')->all();

        $this->assertContains('Evento A', $titles);
        $this->assertNotContains('Evento B', $titles);
    }

    public function test_update_cross_tenant_returns_404(): void
    {
        $companyA = $this->createCompany('Empresa Agenda G');
        $companyB = $this->createCompany('Empresa Agenda H');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $eventB = CalendarEvent::query()->create([
            'company_id' => $companyB->id,
            'title' => 'Evento B',
            'type' => CalendarEvent::TYPE_TASK,
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => CalendarEvent::PRIORITY_NORMAL,
            'starts_at' => now()->addDay(),
            'all_day' => false,
        ]);

        $this->actingAs($adminA)
            ->putJson(route('admin.calendar.events.update', $eventB->id), [
                'title' => 'Update invalido',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDays(2)->toDateTimeString(),
                'all_day' => false,
            ])
            ->assertNotFound();
    }

    public function test_complete_updates_status_and_completed_at(): void
    {
        $company = $this->createCompany('Empresa Agenda I');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $event = $this->createCalendarEvent($company, 'Concluir tarefa');

        $this->actingAs($admin)
            ->patchJson(route('admin.calendar.events.complete', $event->id))
            ->assertOk()
            ->assertJsonPath('message', 'Tarefa concluida com sucesso.');

        $event->refresh();
        $this->assertSame(CalendarEvent::STATUS_COMPLETED, $event->status);
        $this->assertNotNull($event->completed_at);
        $this->assertNull($event->cancelled_at);
    }

    public function test_cancel_updates_status_and_cancelled_at(): void
    {
        $company = $this->createCompany('Empresa Agenda J');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $event = $this->createCalendarEvent($company, 'Cancelar tarefa');

        $this->actingAs($admin)
            ->patchJson(route('admin.calendar.events.cancel', $event->id))
            ->assertOk()
            ->assertJsonPath('message', 'Tarefa cancelada com sucesso.');

        $event->refresh();
        $this->assertSame(CalendarEvent::STATUS_CANCELLED, $event->status);
        $this->assertNotNull($event->cancelled_at);
        $this->assertNull($event->completed_at);
    }

    public function test_destroy_deletes_event(): void
    {
        $company = $this->createCompany('Empresa Agenda K');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $event = $this->createCalendarEvent($company, 'Apagar evento');

        $this->actingAs($admin)
            ->deleteJson(route('admin.calendar.events.destroy', $event->id))
            ->assertOk()
            ->assertJsonPath('message', 'Evento removido com sucesso.');

        $this->assertDatabaseMissing('calendar_events', ['id' => $event->id]);
    }

    public function test_events_ajax_payload_contains_required_structure(): void
    {
        $company = $this->createCompany('Empresa Agenda L');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $admin->forceFill(['calendar_color' => '#2c7be5'])->save();
        $event = $this->createCalendarEvent($company, 'Payload evento');
        $event->forceFill(['user_id' => $admin->id])->save();

        $response = $this->actingAs($admin)
            ->getJson(route('admin.calendar.events', [
                'start' => now()->subDays(2)->toIso8601String(),
                'end' => now()->addDays(10)->toIso8601String(),
            ]))
            ->assertOk();

        $this->assertIsArray($response->json());
        $first = collect($response->json())->firstWhere('id', $event->id);

        $this->assertIsArray($first);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('start', $first);
        $this->assertArrayHasKey('extendedProps', $first);
        $this->assertArrayHasKey('backgroundColor', $first);
        $this->assertArrayHasKey('borderColor', $first);
        $this->assertSame('#2c7be5', strtolower((string) $first['backgroundColor']));
    }

    public function test_overlap_blocks_same_user_in_same_time_window(): void
    {
        $company = $this->createCompany('Empresa Agenda Overlap A');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $responsible = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->createCalendarEvent(
            $company,
            'Evento base',
            userId: (int) $responsible->id,
            startsAt: now()->addDay()->setHour(10)->setMinute(0),
            endsAt: now()->addDay()->setHour(11)->setMinute(0)
        );

        $this->actingAs($admin)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Conflito',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDay()->setHour(10)->setMinute(30)->toDateTimeString(),
                'ends_at' => now()->addDay()->setHour(11)->setMinute(30)->toDateTimeString(),
                'all_day' => false,
                'user_id' => $responsible->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_overlap_allows_different_users_same_time_window(): void
    {
        $company = $this->createCompany('Empresa Agenda Overlap B');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $userA = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $userB = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->createCalendarEvent(
            $company,
            'Evento user A',
            userId: (int) $userA->id,
            startsAt: now()->addDay()->setHour(10)->setMinute(0),
            endsAt: now()->addDay()->setHour(11)->setMinute(0)
        );

        $this->actingAs($admin)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Evento user B',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDay()->setHour(10)->setMinute(30)->toDateTimeString(),
                'ends_at' => now()->addDay()->setHour(11)->setMinute(30)->toDateTimeString(),
                'all_day' => false,
                'user_id' => $userB->id,
            ])
            ->assertCreated();
    }

    public function test_overlap_ignores_cancelled_events(): void
    {
        $company = $this->createCompany('Empresa Agenda Overlap C');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $responsible = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->createCalendarEvent(
            $company,
            'Cancelado',
            userId: (int) $responsible->id,
            startsAt: now()->addDay()->setHour(10)->setMinute(0),
            endsAt: now()->addDay()->setHour(11)->setMinute(0),
            status: CalendarEvent::STATUS_CANCELLED
        );

        $this->actingAs($admin)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Novo no mesmo horario',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDay()->setHour(10)->setMinute(15)->toDateTimeString(),
                'ends_at' => now()->addDay()->setHour(10)->setMinute(45)->toDateTimeString(),
                'all_day' => false,
                'user_id' => $responsible->id,
            ])
            ->assertCreated();
    }

    public function test_update_to_occupied_slot_for_same_user_fails(): void
    {
        $company = $this->createCompany('Empresa Agenda Overlap Update');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $responsible = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $existing = $this->createCalendarEvent(
            $company,
            'Evento fixo',
            userId: (int) $responsible->id,
            startsAt: now()->addDay()->setHour(9)->setMinute(0),
            endsAt: now()->addDay()->setHour(10)->setMinute(0)
        );

        $toUpdate = $this->createCalendarEvent(
            $company,
            'Evento mover',
            userId: (int) $responsible->id,
            startsAt: now()->addDay()->setHour(11)->setMinute(0),
            endsAt: now()->addDay()->setHour(12)->setMinute(0)
        );

        $this->actingAs($admin)
            ->putJson(route('admin.calendar.events.update', $toUpdate->id), [
                'title' => 'Evento mover',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => $existing->starts_at->copy()->addMinutes(15)->toDateTimeString(),
                'ends_at' => $existing->ends_at->copy()->addMinutes(15)->toDateTimeString(),
                'all_day' => false,
                'user_id' => $responsible->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    public function test_overlap_is_not_applied_when_user_id_is_null(): void
    {
        $company = $this->createCompany('Empresa Agenda Overlap D');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->createCalendarEvent(
            $company,
            'Geral 1',
            userId: null,
            startsAt: now()->addDay()->setHour(14)->setMinute(0),
            endsAt: now()->addDay()->setHour(15)->setMinute(0)
        );

        $this->actingAs($admin)
            ->postJson(route('admin.calendar.events.store'), [
                'title' => 'Geral 2',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDay()->setHour(14)->setMinute(15)->toDateTimeString(),
                'ends_at' => now()->addDay()->setHour(14)->setMinute(45)->toDateTimeString(),
                'all_day' => false,
                'user_id' => null,
            ])
            ->assertCreated();
    }

    private function createCalendarEvent(
        Company $company,
        string $title,
        ?int $userId = null,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
        string $status = CalendarEvent::STATUS_PENDING
    ): CalendarEvent
    {
        return CalendarEvent::query()->create([
            'company_id' => $company->id,
            'title' => $title,
            'type' => CalendarEvent::TYPE_TASK,
            'status' => $status,
            'priority' => CalendarEvent::PRIORITY_NORMAL,
            'user_id' => $userId,
            'starts_at' => $startsAt ?? now()->addDay(),
            'ends_at' => $endsAt,
            'all_day' => false,
        ]);
    }

    private function createConstructionSite(Company $company, string $name): ConstructionSite
    {
        $customer = $this->createCustomer($company, 'Cliente '.Str::lower(Str::random(4)));
        $creator = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

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

    private function createSupplier(Company $company, string $name): Supplier
    {
        return Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
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
