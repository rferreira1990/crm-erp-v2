<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\EmailAccount;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_can_create_email_account_and_password_is_encrypted(): void
    {
        $company = $this->createCompany('Empresa Inbox Conta 1');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->post(route('admin.email-accounts.store'), [
            'name' => 'Conta principal',
            'email' => 'inbox@empresa.test',
            'imap_host' => 'imap.empresa.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'inbox@empresa.test',
            'imap_password' => 'SuperSecret-123',
            'imap_folder' => 'INBOX',
            'is_active' => 1,
            'smtp_use_custom_settings' => 1,
            'smtp_from_name' => 'Empresa Inbox',
            'smtp_from_address' => 'smtp@empresa.test',
            'smtp_host' => 'smtp.empresa.test',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'smtp@empresa.test',
            'smtp_password' => 'SmtpSecret-123',
        ]);

        $response->assertRedirect(route('admin.email-accounts.edit'));
        $response->assertSessionHasNoErrors();

        $account = EmailAccount::query()->forCompany((int) $company->id)->firstOrFail();

        $this->assertSame('Conta principal', $account->name);
        $this->assertNotSame('SuperSecret-123', $account->getRawOriginal('imap_password_encrypted'));
        $this->assertSame('SuperSecret-123', $account->resolveImapPassword());
        $this->assertNotSame('SmtpSecret-123', $account->getRawOriginal('smtp_password_encrypted'));
        $this->assertSame('SmtpSecret-123', $account->resolveSmtpPassword());
    }

    public function test_update_without_password_keeps_existing_encrypted_password(): void
    {
        $company = $this->createCompany('Empresa Inbox Conta 2');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $account = new EmailAccount([
            'company_id' => $company->id,
            'name' => 'Conta antiga',
            'email' => 'antiga@empresa.test',
            'imap_host' => 'imap.antiga.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'antiga@empresa.test',
            'imap_folder' => 'INBOX',
            'is_active' => true,
        ]);
        $account->setImapPassword('Secret-Old');
        $account->save();

        $oldEncrypted = (string) $account->getRawOriginal('imap_password_encrypted');

        $response = $this->actingAs($admin)->put(route('admin.email-accounts.update', $account->id), [
            'name' => 'Conta atualizada',
            'email' => 'atualizada@empresa.test',
            'imap_host' => 'imap.nova.test',
            'imap_port' => 993,
            'imap_encryption' => 'tls',
            'imap_username' => 'atualizada@empresa.test',
            'imap_password' => '',
            'imap_folder' => 'INBOX',
            'is_active' => 1,
            'smtp_use_custom_settings' => 1,
            'smtp_from_name' => 'Empresa Atualizada',
            'smtp_from_address' => 'smtp.atualizada@empresa.test',
            'smtp_host' => 'smtp.atualizada.test',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'smtp.atualizada@empresa.test',
            'smtp_password' => '',
        ]);

        $response->assertRedirect(route('admin.email-accounts.edit'));
        $response->assertSessionHasNoErrors();

        $account->refresh();

        $this->assertSame($oldEncrypted, (string) $account->getRawOriginal('imap_password_encrypted'));
        $this->assertSame('Secret-Old', $account->resolveImapPassword());
        $this->assertSame('Conta atualizada', $account->name);
    }

    public function test_user_without_permission_cannot_manage_email_accounts(): void
    {
        $company = $this->createCompany('Empresa Inbox Conta Sem Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->actingAs($user)->get(route('admin.email-accounts.edit'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.email-accounts.store'), [
            'name' => 'Conta proibida',
            'email' => 'proibida@empresa.test',
            'imap_host' => 'imap.proibida.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'proibida@empresa.test',
            'imap_password' => '12345678',
            'imap_folder' => 'INBOX',
            'is_active' => 1,
            'smtp_use_custom_settings' => 0,
        ])->assertForbidden();
    }

    public function test_cross_tenant_email_account_update_returns_404(): void
    {
        $companyA = $this->createCompany('Empresa Inbox Conta Tenant A');
        $companyB = $this->createCompany('Empresa Inbox Conta Tenant B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $accountB = EmailAccount::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Conta B',
            'email' => 'b@empresa.test',
            'imap_host' => 'imap.b.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'b@empresa.test',
            'imap_password_encrypted' => encrypt('Secret-B'),
            'imap_folder' => 'INBOX',
            'is_active' => true,
        ]);

        $this->actingAs($adminA)->put(route('admin.email-accounts.update', $accountB->id), [
            'name' => 'Tentativa',
            'email' => 'tentativa@empresa.test',
            'imap_host' => 'imap.tentativa.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'tentativa@empresa.test',
            'imap_folder' => 'INBOX',
            'is_active' => 1,
            'smtp_use_custom_settings' => 1,
            'smtp_from_name' => 'Empresa Tentativa',
            'smtp_from_address' => 'smtp.tentativa@empresa.test',
            'smtp_host' => 'smtp.tentativa.test',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'smtp.tentativa@empresa.test',
            'smtp_password' => '',
        ])->assertNotFound();
    }

    public function test_password_is_never_rendered_in_edit_view(): void
    {
        $company = $this->createCompany('Empresa Inbox Conta View');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $account = new EmailAccount([
            'company_id' => $company->id,
            'name' => 'Conta segura',
            'email' => 'segura@empresa.test',
            'imap_host' => 'imap.segura.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'segura@empresa.test',
            'imap_folder' => 'INBOX',
            'is_active' => true,
        ]);
        $account->setImapPassword('MinhaPasswordUltraSecreta');
        $account->save();

        $response = $this->actingAs($admin)->get(route('admin.email-accounts.edit'));

        $response->assertOk();
        $response->assertSee('name="imap_password"', false);
        $response->assertDontSee('MinhaPasswordUltraSecreta', false);
        $response->assertDontSee((string) $account->getRawOriginal('imap_password_encrypted'), false);
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
