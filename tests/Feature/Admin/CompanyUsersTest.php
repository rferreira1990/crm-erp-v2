<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_sees_only_users_from_own_company(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');

        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $sameCompanyUser = $this->createCompanyUser($companyA, User::ROLE_COMPANY_USER);
        $otherCompanyUser = $this->createCompanyUser($companyB, User::ROLE_COMPANY_USER);
        $superAdmin = User::query()->where('is_super_admin', true)->firstOrFail();

        $response = $this->actingAs($adminA)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee($sameCompanyUser->email);
        $response->assertSee($adminA->email);
        $response->assertDontSee($otherCompanyUser->email);
        $response->assertDontSee($superAdmin->email);
    }

    public function test_company_users_list_filters_and_pagination_keep_query_string(): void
    {
        $company = $this->createCompany('Empresa Filtros');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN, true, 'admin-filtros@empresa.test');

        foreach (range(1, 25) as $i) {
            $this->createCompanyUser(
                $company,
                User::ROLE_COMPANY_ADMIN,
                true,
                "alice{$i}@empresa.test",
                "Alice {$i}"
            );
        }

        $this->createCompanyUser($company, User::ROLE_COMPANY_USER, false, 'bob@empresa.test', 'Bob');

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'q' => 'alice',
            'status' => 'active',
            'role' => User::ROLE_COMPANY_ADMIN,
        ]));

        $response->assertOk();
        $response->assertSee('Alice 1');
        $response->assertDontSee('Bob');
        $response->assertSee('q=alice', false);
        $response->assertSee('status=active', false);
        $response->assertSee('role='.User::ROLE_COMPANY_ADMIN, false);
    }

    public function test_company_admin_can_update_role_and_it_replaces_previous_role(): void
    {
        $company = $this->createCompany('Empresa Roles');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $target = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $target->id), [
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status');
        $this->assertTrue($target->refresh()->hasRole(User::ROLE_COMPANY_ADMIN));
        $this->assertFalse($target->hasRole(User::ROLE_COMPANY_USER));
    }

    public function test_company_admin_can_update_hourly_cost_with_role(): void
    {
        $company = $this->createCompany('Empresa Custo Hora');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $target = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $target->id), [
            'role' => User::ROLE_COMPANY_USER,
            'hourly_cost' => '27,50',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame('27.50', $target->refresh()->hourly_cost);
    }

    public function test_company_admin_can_clear_hourly_cost(): void
    {
        $company = $this->createCompany('Empresa Custo Null');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $target = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $target->forceFill(['hourly_cost' => 15.25])->save();

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $target->id), [
            'role' => User::ROLE_COMPANY_USER,
            'hourly_cost' => '',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertNull($target->refresh()->hourly_cost);
    }

    public function test_company_admin_can_update_own_hourly_cost_without_changing_role(): void
    {
        $company = $this->createCompany('Empresa Custo Proprio');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $admin->id), [
            'role' => User::ROLE_COMPANY_ADMIN,
            'hourly_cost' => '32,00',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame('32.00', $admin->refresh()->hourly_cost);
        $this->assertTrue($admin->hasRole(User::ROLE_COMPANY_ADMIN));
    }

    public function test_role_update_rejects_role_outside_internal_context(): void
    {
        $company = $this->createCompany('Empresa Role Invalida');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $target = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update', $target->id), [
                'role' => 'super_admin',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors('role');
        $this->assertTrue($target->refresh()->hasRole(User::ROLE_COMPANY_USER));
    }

    public function test_role_update_cannot_target_user_from_another_company(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $targetB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_USER);

        $response = $this->actingAs($adminA)->patch(route('admin.users.update', $targetB->id), [
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        $response->assertNotFound();
    }

    public function test_cannot_remove_company_admin_role_from_last_active_admin(): void
    {
        $company = $this->createCompany('Empresa Last Admin');
        $targetAdmin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $manager = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $manager->givePermissionTo('company.users.update');

        $response = $this->actingAs($manager)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update', $targetAdmin->id), [
                'role' => User::ROLE_COMPANY_USER,
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors('role');
        $this->assertTrue($targetAdmin->refresh()->hasRole(User::ROLE_COMPANY_ADMIN));
    }

    public function test_can_remove_admin_role_when_company_has_more_than_one_active_admin(): void
    {
        $company = $this->createCompany('Empresa Multi Admin');
        $adminA = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($adminA)->patch(route('admin.users.update', $adminB->id), [
            'role' => User::ROLE_COMPANY_USER,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertTrue($adminB->refresh()->hasRole(User::ROLE_COMPANY_USER));
        $this->assertFalse($adminB->hasRole(User::ROLE_COMPANY_ADMIN));
    }

    public function test_company_admin_can_toggle_active_for_same_company_user(): void
    {
        $company = $this->createCompany('Empresa Toggle');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $target = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $disableResponse = $this->actingAs($admin)->patch(route('admin.users.toggle-active', $target->id));
        $disableResponse->assertRedirect(route('admin.users.index'));
        $this->assertFalse($target->fresh()->is_active);

        $enableResponse = $this->actingAs($admin)->patch(route('admin.users.toggle-active', $target->id));
        $enableResponse->assertRedirect(route('admin.users.index'));
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_cannot_disable_own_account(): void
    {
        $company = $this->createCompany('Empresa Own');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.toggle-active', $admin->id));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors('user');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_cannot_disable_last_active_company_admin(): void
    {
        $company = $this->createCompany('Empresa Last Active');
        $targetAdmin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $manager = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $manager->givePermissionTo('company.users.update');

        $response = $this->actingAs($manager)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.toggle-active', $targetAdmin->id));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors('user');
        $this->assertTrue($targetAdmin->fresh()->is_active);
    }

    public function test_can_disable_admin_if_another_active_admin_exists(): void
    {
        $company = $this->createCompany('Empresa Two Admins');
        $adminA = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($adminA)->patch(route('admin.users.toggle-active', $adminB->id));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertFalse($adminB->fresh()->is_active);
    }

    public function test_toggle_active_cannot_target_user_from_another_company(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $targetB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_USER);

        $response = $this->actingAs($adminA)->patch(route('admin.users.toggle-active', $targetB->id));

        $response->assertNotFound();
    }

    public function test_user_without_permissions_cannot_access_company_users_module_actions(): void
    {
        $company = $this->createCompany('Empresa No Perms');
        $noPermUser = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $target = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->actingAs($noPermUser)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($noPermUser)->patch(route('admin.users.update', $target->id), ['role' => User::ROLE_COMPANY_ADMIN])->assertForbidden();
        $this->actingAs($noPermUser)->patch(route('admin.users.toggle-active', $target->id))->assertForbidden();
    }

    public function test_superadmin_cannot_access_company_users_module(): void
    {
        $superAdmin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($superAdmin)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_company_users_module_routes(): void
    {
        $company = $this->createCompany('Empresa Guest');
        $target = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->patch(route('admin.users.update', $target->id), [
            'role' => User::ROLE_COMPANY_ADMIN,
        ])->assertRedirect(route('login'));
        $this->patch(route('admin.users.toggle-active', $target->id))
            ->assertRedirect(route('login'));
    }

    public function test_reactivating_user_returns_success_feedback_and_updates_state(): void
    {
        $company = $this->createCompany('Empresa Reativar');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $target = $this->createCompanyUser($company, User::ROLE_COMPANY_USER, false);

        $response = $this->actingAs($admin)->patch(route('admin.users.toggle-active', $target->id));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'ativado'));
        $this->assertTrue($target->fresh()->is_active);
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
        ?string $email = null,
        ?string $name = null
    ): User {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => $isActive,
            'email' => $email ?? Str::lower(Str::random(8)).'@example.test',
            'name' => $name ?? 'User '.Str::random(5),
        ]);

        $user->syncRoles([$role]);

        return $user;
    }
}
