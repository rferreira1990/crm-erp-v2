<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailMessageAttachment;
use App\Models\User;
use App\Services\Admin\EmailInboxSyncService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class EmailInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_inbox_lists_only_messages_from_current_company(): void
    {
        $companyA = $this->createCompany('Empresa Inbox Lista A');
        $companyB = $this->createCompany('Empresa Inbox Lista B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $accountA = $this->createEmailAccount($companyA, 'Conta A');
        $accountB = $this->createEmailAccount($companyB, 'Conta B');

        $messageA = $this->createMessage($companyA, $accountA, 'Assunto A', false, false);
        $messageB = $this->createMessage($companyB, $accountB, 'Assunto B', false, false);

        $response = $this->actingAs($adminA)->get(route('admin.email-inbox.index'));

        $response->assertOk();
        $response->assertSee($messageA->subjectLabel());
        $response->assertDontSee($messageB->subjectLabel());
    }

    public function test_inbox_filters_by_search_has_attachments_and_unread(): void
    {
        $company = $this->createCompany('Empresa Inbox Filtros');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $account = $this->createEmailAccount($company, 'Conta filtros');

        $invoice = $this->createMessage($company, $account, 'Fatura abril', false, true);
        $welcome = $this->createMessage($company, $account, 'Bem-vindo', true, false);

        $this->actingAs($admin)
            ->get(route('admin.email-inbox.index', ['q' => 'fatura']))
            ->assertOk()
            ->assertSee($invoice->subjectLabel())
            ->assertDontSee($welcome->subjectLabel());

        $this->actingAs($admin)
            ->get(route('admin.email-inbox.index', ['has_attachments' => 1]))
            ->assertOk()
            ->assertSee($invoice->subjectLabel())
            ->assertDontSee($welcome->subjectLabel());

        $this->actingAs($admin)
            ->get(route('admin.email-inbox.index', ['unread' => 1]))
            ->assertOk()
            ->assertSee($invoice->subjectLabel())
            ->assertDontSee($welcome->subjectLabel());
    }

    public function test_show_message_from_other_company_returns_404(): void
    {
        $companyA = $this->createCompany('Empresa Inbox Show A');
        $companyB = $this->createCompany('Empresa Inbox Show B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $accountB = $this->createEmailAccount($companyB, 'Conta B');
        $messageB = $this->createMessage($companyB, $accountB, 'Mensagem B', false, false);

        $this->actingAs($adminA)
            ->get(route('admin.email-messages.show', $messageB->id))
            ->assertNotFound();
    }

    public function test_show_message_renders_modern_details_and_attachments(): void
    {
        $company = $this->createCompany('Empresa Inbox Detalhe');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $account = $this->createEmailAccount($company, 'Conta Detalhe');
        $message = $this->createMessage($company, $account, 'Assunto detalhe', false, true);
        $attachment = $this->createAttachment($company, $message, 'contrato.pdf', 'email/'.$company->id.'/attachments/contrato.pdf');

        Storage::fake('local');
        Storage::disk('local')->put((string) $attachment->storage_path, 'conteudo');

        $response = $this->actingAs($admin)
            ->get(route('admin.email-messages.show', $message->id));

        $response->assertOk();
        $response->assertSee('Assunto detalhe');
        $response->assertSee('Sender');
        $response->assertSee('Anexos');
        $response->assertSee('contrato.pdf');
    }

    public function test_show_message_without_attachments_uses_safe_html_fallback(): void
    {
        $company = $this->createCompany('Empresa Inbox Sem Anexos');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $account = $this->createEmailAccount($company, 'Conta Sem Anexos');

        $message = EmailMessage::query()->create([
            'company_id' => $company->id,
            'email_account_id' => $account->id,
            'message_uid' => (string) random_int(1000, 999999),
            'message_id' => '<'.Str::random(24).'@example.test>',
            'folder' => 'INBOX',
            'from_email' => 'sender@example.test',
            'from_name' => 'Sender',
            'to_email' => 'receiver@example.test',
            'to_name' => 'Receiver',
            'subject' => 'Mensagem sem anexos',
            'snippet' => null,
            'body_text' => null,
            'body_html' => '<p>Texto seguro</p><script>alert(1)</script>',
            'received_at' => now(),
            'is_seen' => false,
            'has_attachments' => false,
            'raw_headers' => ['header' => 'Cc: suporte@example.test'],
            'synced_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.email-messages.show', $message->id));

        $response->assertOk();
        $response->assertSee('Mensagem sem anexos');
        $response->assertSee('Sem anexos.');
        $response->assertSee('Texto seguro');
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('alert(1)');
        $response->assertSee('suporte@example.test');
    }

    public function test_manual_sync_calls_sync_service(): void
    {
        $company = $this->createCompany('Empresa Inbox Sync');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $account = $this->createEmailAccount($company, 'Conta Sync');

        $mock = Mockery::mock(EmailInboxSyncService::class);
        $mock->shouldReceive('syncLatestInbox')
            ->once()
            ->withArgs(function (EmailAccount $givenAccount, int $limit) use ($account): bool {
                return (int) $givenAccount->id === (int) $account->id && $limit === 30;
            })
            ->andReturn([
                'processed' => 10,
                'created' => 6,
                'updated' => 4,
            ]);
        $this->app->instance(EmailInboxSyncService::class, $mock);

        $response = $this->actingAs($admin)
            ->post(route('admin.email-inbox.sync'));

        $response->assertRedirect(route('admin.email-inbox.index'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
    }

    public function test_download_attachment_from_other_company_returns_404(): void
    {
        Storage::fake('local');

        $companyA = $this->createCompany('Empresa Inbox Attach A');
        $companyB = $this->createCompany('Empresa Inbox Attach B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $accountB = $this->createEmailAccount($companyB, 'Conta B');
        $messageB = $this->createMessage($companyB, $accountB, 'Mensagem B', true, true);
        $attachmentB = $this->createAttachment($companyB, $messageB, 'ficheiro.pdf', 'email/'.$companyB->id.'/attachments/ficheiro.pdf');

        Storage::disk('local')->put((string) $attachmentB->storage_path, 'conteudo');

        $this->actingAs($adminB)
            ->get(route('admin.email-attachments.download', [$messageB->id, $attachmentB->id]))
            ->assertOk();

        $this->actingAs($adminA)
            ->get(route('admin.email-attachments.download', [$messageB->id, $attachmentB->id]))
            ->assertNotFound();
    }

    public function test_attachment_download_blocks_path_traversal(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Inbox Path');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $account = $this->createEmailAccount($company, 'Conta Path');
        $message = $this->createMessage($company, $account, 'Path test', true, true);
        $attachment = $this->createAttachment($company, $message, 'secret.txt', '../secrets/secret.txt');

        $this->actingAs($admin)
            ->get(route('admin.email-attachments.download', [$message->id, $attachment->id]))
            ->assertNotFound();
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

    private function createEmailAccount(Company $company, string $name): EmailAccount
    {
        $account = new EmailAccount([
            'company_id' => $company->id,
            'name' => $name,
            'email' => Str::slug($name).'-'.$company->id.'@example.test',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => Str::slug($name).'.'.$company->id,
            'imap_folder' => 'INBOX',
            'is_active' => true,
        ]);
        $account->setImapPassword('Secret-123');
        $account->save();

        return $account;
    }

    private function createMessage(
        Company $company,
        EmailAccount $account,
        string $subject,
        bool $isSeen,
        bool $hasAttachments
    ): EmailMessage {
        return EmailMessage::query()->create([
            'company_id' => $company->id,
            'email_account_id' => $account->id,
            'message_uid' => (string) random_int(1000, 999999),
            'message_id' => '<'.Str::random(24).'@example.test>',
            'folder' => 'INBOX',
            'from_email' => 'sender@example.test',
            'from_name' => 'Sender',
            'to_email' => 'receiver@example.test',
            'to_name' => 'Receiver',
            'subject' => $subject,
            'snippet' => 'Snippet '.$subject,
            'body_text' => 'Body '.$subject,
            'body_html' => '<p>Body '.$subject.'</p>',
            'received_at' => now(),
            'is_seen' => $isSeen,
            'has_attachments' => $hasAttachments,
            'raw_headers' => ['header' => 'X-Test: 1'],
            'synced_at' => now(),
        ]);
    }

    private function createAttachment(
        Company $company,
        EmailMessage $message,
        string $filename,
        ?string $storagePath
    ): EmailMessageAttachment {
        return EmailMessageAttachment::query()->create([
            'company_id' => $company->id,
            'email_message_id' => $message->id,
            'filename' => $filename,
            'mime_type' => 'application/octet-stream',
            'size_bytes' => 128,
            'storage_path' => $storagePath,
            'content_id' => null,
            'is_inline' => false,
        ]);
    }
}
