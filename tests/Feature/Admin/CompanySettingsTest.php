<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\CompanySmtpTestMail;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_company_admin_can_access_company_settings_page(): void
    {
        $company = $this->createCompany('Empresa Settings Admin');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.company-settings.edit'));

        $response->assertOk();
        $response->assertSee('Configuracao da Empresa');
        $response->assertSee($company->name);
    }

    public function test_company_user_cannot_access_company_settings_page(): void
    {
        $company = $this->createCompany('Empresa Settings User');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->actingAs($user)->get(route('admin.company-settings.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.company-settings.update'), [
            'mail_use_custom_settings' => 0,
        ])->assertForbidden();
        $this->actingAs($user)->post(route('admin.company-settings.test-smtp'))->assertForbidden();
    }

    public function test_superadmin_cannot_access_company_settings_page(): void
    {
        $superAdmin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($superAdmin)->get(route('admin.company-settings.edit'))->assertForbidden();
    }

    public function test_company_admin_updates_general_data_and_cannot_change_name(): void
    {
        $company = $this->createCompany('Empresa Nome Fixo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->put(route('admin.company-settings.update'), [
            'name' => 'Tentativa de Alterar Nome',
            'address' => 'Rua Central 123',
            'locality' => 'Benfica',
            'city' => 'Lisboa',
            'postal_code' => '1500-001',
            'phone' => '210000001',
            'mobile' => '910000001',
            'email' => 'geral@empresa-fixa.pt',
            'website' => 'https://www.empresa-fixa.pt',
            'mail_use_custom_settings' => 0,
        ]);

        $response->assertRedirect(route('admin.company-settings.edit'));
        $company->refresh();

        $this->assertSame('Empresa Nome Fixo', $company->name);
        $this->assertSame('Rua Central 123', $company->address);
        $this->assertSame('Benfica', $company->locality);
        $this->assertSame('Lisboa', $company->city);
        $this->assertSame('1500-001', $company->postal_code);
        $this->assertSame('210000001', $company->phone);
        $this->assertSame('910000001', $company->mobile);
        $this->assertSame('geral@empresa-fixa.pt', $company->email);
        $this->assertSame('https://www.empresa-fixa.pt', $company->website);
    }

    public function test_company_admin_updates_bank_data(): void
    {
        $company = $this->createCompany('Empresa Banco');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->put(route('admin.company-settings.update'), [
            'bank_name' => 'Banco Teste',
            'iban' => 'PT50 0000 0000 0000 0000 0000 0',
            'bic_swift' => 'CGDIPTPL',
            'mail_use_custom_settings' => 0,
        ]);

        $response->assertRedirect(route('admin.company-settings.edit'));
        $company->refresh();

        $this->assertSame('Banco Teste', $company->bank_name);
        $this->assertSame('PT50000000000000000000000', $company->iban);
        $this->assertSame('CGDIPTPL', $company->bic_swift);
    }

    public function test_company_admin_can_upload_replace_and_remove_logo(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Logo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $firstLogo = UploadedFile::fake()->image('logo1.png', 200, 80);
        $this->actingAs($admin)->put(route('admin.company-settings.update'), [
            'logo' => $firstLogo,
            'mail_use_custom_settings' => 0,
        ])->assertRedirect(route('admin.company-settings.edit'));

        $company->refresh();
        $firstPath = (string) $company->logo_path;
        $this->assertNotSame('', $firstPath);
        Storage::disk('local')->assertExists($firstPath);

        $secondLogo = UploadedFile::fake()->image('logo2.png', 240, 90);
        $this->actingAs($admin)->put(route('admin.company-settings.update'), [
            'logo' => $secondLogo,
            'mail_use_custom_settings' => 0,
        ])->assertRedirect(route('admin.company-settings.edit'));

        $company->refresh();
        $secondPath = (string) $company->logo_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);

        $this->actingAs($admin)->put(route('admin.company-settings.update'), [
            'remove_logo' => 1,
            'mail_use_custom_settings' => 0,
        ])->assertRedirect(route('admin.company-settings.edit'));

        $company->refresh();
        $this->assertNull($company->logo_path);
        Storage::disk('local')->assertMissing($secondPath);
    }

    public function test_company_admin_updates_email_settings_default_and_custom_with_encrypted_password(): void
    {
        $company = $this->createCompany('Empresa SMTP');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)->put(route('admin.company-settings.update'), [
            'mail_use_custom_settings' => 0,
        ])->assertRedirect(route('admin.company-settings.edit'));

        $company->refresh();
        $this->assertFalse($company->mail_use_custom_settings);

        $plainPassword = 'smtp-secret-123';
        $this->actingAs($admin)->put(route('admin.company-settings.update'), [
            'mail_use_custom_settings' => 1,
            'mail_from_name' => 'Empresa SMTP',
            'mail_from_address' => 'smtp@empresa.test',
            'mail_host' => 'smtp.empresa.test',
            'mail_port' => 587,
            'mail_username' => 'smtp-user',
            'mail_password' => $plainPassword,
            'mail_encryption' => 'tls',
        ])->assertRedirect(route('admin.company-settings.edit'));

        $company->refresh();
        $this->assertTrue($company->mail_use_custom_settings);
        $this->assertSame('smtp@empresa.test', $company->mail_from_address);
        $this->assertSame(587, (int) $company->mail_port);
        $this->assertSame('tls', $company->mail_encryption);
        $this->assertSame($plainPassword, $company->mail_password);

        $storedPassword = DB::table('companies')
            ->where('id', $company->id)
            ->value('mail_password');
        $this->assertIsString($storedPassword);
        $this->assertNotSame($plainPassword, $storedPassword);
    }

    public function test_company_admin_can_send_smtp_test_with_default_and_custom_mode(): void
    {
        Mail::fake();

        $company = $this->createCompany('Empresa Teste SMTP');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $company->forceFill(['email' => 'empresa@teste-smtp.pt'])->save();

        $this->actingAs($admin)->post(route('admin.company-settings.test-smtp'), [])
            ->assertRedirect(route('admin.company-settings.edit'))
            ->assertSessionHas('status');
        Mail::assertSent(CompanySmtpTestMail::class);

        $this->actingAs($admin)->put(route('admin.company-settings.update'), [
            'mail_use_custom_settings' => 1,
            'mail_from_name' => 'Empresa Teste SMTP',
            'mail_from_address' => 'custom@teste-smtp.pt',
            'mail_host' => 'smtp.custom.pt',
            'mail_port' => 465,
            'mail_username' => 'custom-user',
            'mail_password' => 'custom-pass',
            'mail_encryption' => 'ssl',
        ])->assertRedirect(route('admin.company-settings.edit'));

        $this->actingAs($admin)->post(route('admin.company-settings.test-smtp'), [
            'test_email' => 'destino@teste-smtp.pt',
        ])->assertRedirect(route('admin.company-settings.edit'))
            ->assertSessionHas('status');

        Mail::assertSent(CompanySmtpTestMail::class, 2);
    }

    public function test_smtp_test_returns_error_feedback_when_transport_fails(): void
    {
        $company = $this->createCompany('Empresa SMTP Falha');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        Mail::shouldReceive('to')
            ->once()
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new TransportException('Connection timed out'));

        $response = $this->actingAs($admin)->post(route('admin.company-settings.test-smtp'), [
            'test_email' => 'destino@falha-smtp.pt',
        ]);

        $response->assertRedirect(route('admin.company-settings.edit'));
        $response->assertSessionHasErrors('smtp_test');
    }

    public function test_company_settings_update_is_scoped_to_authenticated_company(): void
    {
        $companyA = $this->createCompany('Empresa A Scope');
        $companyB = $this->createCompany('Empresa B Scope');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($adminA)->put(route('admin.company-settings.update'), [
            'address' => 'Rua Empresa A',
            'mail_use_custom_settings' => 0,
        ])->assertRedirect(route('admin.company-settings.edit'));

        $this->assertSame('Rua Empresa A', $companyA->fresh()->address);
        $this->assertNotSame('Rua Empresa A', (string) $companyB->fresh()->address);
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
