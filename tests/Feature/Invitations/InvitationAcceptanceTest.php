<?php

namespace Tests\Feature\Invitations;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_acceptance_fails_when_email_already_exists_globally_even_if_invitation_is_for_another_company(): void
    {
        $companyA = $this->createCompany('Empresa Convite A');
        $companyB = $this->createCompany('Empresa Convite B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $existingUserInOtherCompany = $this->createCompanyUser(
            $companyB,
            User::ROLE_COMPANY_USER,
            true,
            'global.unique@gmail.com'
        );

        $plainToken = 'plain-invite-token';
        $invitation = Invitation::query()->create([
            'company_id' => $companyA->id,
            'invited_by' => $adminA->id,
            'email' => 'global.unique@gmail.com',
            'role' => User::ROLE_COMPANY_USER,
            'token' => Invitation::hashToken($plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        $this->get(route('invitations.accept.create', ['token' => $plainToken]))
            ->assertOk()
            ->assertSee('global.unique@gmail.com');

        $response = $this->post(route('invitations.accept.store'), [
            'token' => $plainToken,
            'name' => 'Novo Utilizador',
            'password' => 'StrongPass!123',
            'password_confirmation' => 'StrongPass!123',
        ]);

        $response->assertRedirect(route('invitations.accept.create', ['token' => $plainToken]));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'company_id' => $companyA->id,
            'email' => 'global.unique@gmail.com',
        ]);
        $this->assertSame($companyB->id, $existingUserInOtherCompany->refresh()->company_id);
        $this->assertNull($invitation->refresh()->accepted_at);
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
