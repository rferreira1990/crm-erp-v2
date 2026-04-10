<?php

namespace Tests\Feature\Admin;

use App\Mail\SuperAdmin\CompanyAdminInvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyUserInvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_can_create_internal_invitation_for_own_company(): void
    {
        Mail::fake();

        $company = $this->createCompany('Empresa Convites');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.user-invitations.store'), [
            'email' => 'novo.utilizador@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('invitations', [
            'company_id' => $company->id,
            'email' => 'novo.utilizador@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
        ]);

        Mail::assertSent(CompanyAdminInvitationMail::class, function (CompanyAdminInvitationMail $mail): bool {
            return $mail->invitation->email === 'novo.utilizador@gmail.com';
        });
    }

    public function test_invitation_rejects_role_outside_internal_context(): void
    {
        Mail::fake();

        $company = $this->createCompany('Empresa Role');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->from(route('admin.user-invitations.create'))
            ->post(route('admin.user-invitations.store'), [
                'email' => 'role.invalida@gmail.com',
                'role' => 'super_admin',
            ]);

        $response->assertRedirect(route('admin.user-invitations.create'));
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('invitations', [
            'email' => 'role.invalida@gmail.com',
        ]);
        Mail::assertNothingSent();
    }

    public function test_invitation_rejects_duplicate_pending_for_same_email_company_and_role(): void
    {
        Mail::fake();

        $company = $this->createCompany('Empresa Duplicados');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        Invitation::query()->create([
            'company_id' => $company->id,
            'invited_by' => $admin->id,
            'email' => 'dup@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
            'token' => Invitation::hashToken('existing-dup-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.user-invitations.create'))
            ->post(route('admin.user-invitations.store'), [
                'email' => 'dup@gmail.com',
                'role' => User::ROLE_COMPANY_USER,
            ]);

        $response->assertRedirect(route('admin.user-invitations.create'));
        $response->assertSessionHasErrors('email');
        $this->assertSame(1, Invitation::query()->where('company_id', $company->id)->where('email', 'dup@gmail.com')->count());
        Mail::assertNothingSent();
    }

    public function test_invitation_rejects_email_already_existing_in_same_company(): void
    {
        Mail::fake();

        $company = $this->createCompany('Empresa Existing Same');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createCompanyUser($company, User::ROLE_COMPANY_USER, true, 'existente@gmail.com');

        $response = $this->actingAs($admin)
            ->from(route('admin.user-invitations.create'))
            ->post(route('admin.user-invitations.store'), [
                'email' => 'existente@gmail.com',
                'role' => User::ROLE_COMPANY_USER,
            ]);

        $response->assertRedirect(route('admin.user-invitations.create'));
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('invitations', [
            'company_id' => $company->id,
            'email' => 'existente@gmail.com',
        ]);
        Mail::assertNothingSent();
    }

    public function test_invitation_allows_email_existing_in_other_company_based_on_current_rules(): void
    {
        Mail::fake();

        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $this->createCompanyUser($companyB, User::ROLE_COMPANY_USER, true, 'partilhado@gmail.com');

        $response = $this->actingAs($adminA)->post(route('admin.user-invitations.store'), [
            'email' => 'partilhado@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('invitations', [
            'company_id' => $companyA->id,
            'email' => 'partilhado@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
        ]);
    }

    public function test_company_admin_can_cancel_pending_invitation_of_own_company(): void
    {
        $company = $this->createCompany('Empresa Cancelar');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $invitation = Invitation::query()->create([
            'company_id' => $company->id,
            'invited_by' => $admin->id,
            'email' => 'pending@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
            'token' => Invitation::hashToken('pending-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.user-invitations.destroy', $invitation->id));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status');
        $this->assertNotNull($invitation->refresh()->cancelled_at);
    }

    public function test_cannot_cancel_invitation_from_other_company(): void
    {
        $companyA = $this->createCompany('Empresa A');
        $companyB = $this->createCompany('Empresa B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $invitationB = Invitation::query()->create([
            'company_id' => $companyB->id,
            'invited_by' => $adminB->id,
            'email' => 'other@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
            'token' => Invitation::hashToken('other-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($adminA)->delete(route('admin.user-invitations.destroy', $invitationB->id));

        $response->assertNotFound();
        $this->assertNull($invitationB->fresh()->cancelled_at);
    }

    public function test_cannot_cancel_non_pending_invitation(): void
    {
        $company = $this->createCompany('Empresa Non Pending');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $acceptedInvitation = Invitation::query()->create([
            'company_id' => $company->id,
            'invited_by' => $admin->id,
            'email' => 'accepted@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
            'token' => Invitation::hashToken('accepted-token'),
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.user-invitations.destroy', $acceptedInvitation->id));

        $response->assertForbidden();
    }

    public function test_user_without_permissions_cannot_create_or_cancel_internal_invitations(): void
    {
        $company = $this->createCompany('Empresa No Perm Convites');
        $noPermUser = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $invitation = Invitation::query()->create([
            'company_id' => $company->id,
            'invited_by' => $admin->id,
            'email' => 'cancelar@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
            'token' => Invitation::hashToken('cancel-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($noPermUser)->get(route('admin.user-invitations.create'))->assertForbidden();
        $this->actingAs($noPermUser)->post(route('admin.user-invitations.store'), [
            'email' => 'sem-perm@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
        ])->assertForbidden();
        $this->actingAs($noPermUser)->delete(route('admin.user-invitations.destroy', $invitation->id))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_internal_invitation_routes(): void
    {
        $company = $this->createCompany('Empresa Guest Convites');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $invitation = Invitation::query()->create([
            'company_id' => $company->id,
            'invited_by' => $admin->id,
            'email' => 'guest-cancel@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
            'token' => Invitation::hashToken('guest-cancel-token'),
            'expires_at' => now()->addDays(7),
        ]);

        $this->get(route('admin.user-invitations.create'))->assertRedirect(route('login'));
        $this->post(route('admin.user-invitations.store'), [
            'email' => 'guest-store@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
        ])->assertRedirect(route('login'));
        $this->delete(route('admin.user-invitations.destroy', $invitation->id))
            ->assertRedirect(route('login'));
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

