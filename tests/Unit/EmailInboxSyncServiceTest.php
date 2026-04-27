<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailMessageAttachment;
use App\Services\Admin\EmailAccountConnectionService;
use App\Services\Admin\EmailInboxSyncService;
use App\Services\Admin\EmailMessageSanitizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmailInboxSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_and_updates_messages_without_duplicates(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Unit Inbox 1');
        $account = $this->createAccount($company);

        $connection = Mockery::mock(EmailAccountConnectionService::class);
        $connection->shouldReceive('fetchLatestInbox')
            ->once()
            ->andReturn([
                $this->remoteMessagePayload(uid: '100', subject: 'Assunto inicial'),
            ]);
        $connection->shouldReceive('fetchLatestInbox')
            ->once()
            ->andReturn([
                $this->remoteMessagePayload(uid: '100', subject: 'Assunto atualizado'),
            ]);

        $service = new EmailInboxSyncService($connection, new EmailMessageSanitizerService());

        $first = $service->syncLatestInbox($account, 100);
        $second = $service->syncLatestInbox($account, 100);

        $this->assertSame(['processed' => 1, 'created' => 1, 'updated' => 0], $first);
        $this->assertSame(['processed' => 1, 'created' => 0, 'updated' => 1], $second);
        $this->assertSame(1, EmailMessage::query()->where('company_id', $company->id)->count());

        $message = EmailMessage::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('Assunto atualizado', $message->subject);
        $this->assertSame(1, $message->attachments()->count());
        $attachment = EmailMessageAttachment::query()->where('email_message_id', $message->id)->firstOrFail();
        $this->assertNotNull($attachment->storage_path);
        $this->assertTrue(Storage::disk('local')->exists((string) $attachment->storage_path));

        $account->refresh();
        $this->assertNotNull($account->last_synced_at);
        $this->assertNull($account->last_error);
    }

    public function test_sync_failure_stores_last_error_on_email_account(): void
    {
        $company = $this->createCompany('Empresa Unit Inbox 2');
        $account = $this->createAccount($company);

        $connection = Mockery::mock(EmailAccountConnectionService::class);
        $connection->shouldReceive('fetchLatestInbox')
            ->once()
            ->andThrow(new RuntimeException('Ligacao IMAP timeout'));

        $service = new EmailInboxSyncService($connection, new EmailMessageSanitizerService());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ligacao IMAP timeout');

        try {
            $service->syncLatestInbox($account, 100);
        } finally {
            $account->refresh();
            $this->assertStringContainsString('Ligacao IMAP timeout', (string) $account->last_error);
        }
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }

    private function createAccount(Company $company): EmailAccount
    {
        $account = new EmailAccount([
            'company_id' => $company->id,
            'name' => 'Conta',
            'email' => 'inbox.'.$company->id.'@example.test',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'inbox.'.$company->id,
            'imap_folder' => 'INBOX',
            'is_active' => true,
        ]);
        $account->setImapPassword('Secret-123');
        $account->save();

        return $account;
    }

    /**
     * @return array{
     *   uid:string,
     *   message_id:string,
     *   folder:string,
     *   from_email:string,
     *   from_name:string,
     *   to_email:string,
     *   to_name:string,
     *   subject:string,
     *   body_text:string,
     *   body_html:string,
     *   snippet:string,
     *   received_at:string,
     *   is_seen:bool,
     *   has_attachments:bool,
     *   raw_headers:array{header:string},
     *   attachments:array<int, array{
     *     filename:string,
     *     mime_type:string,
     *     size_bytes:int,
     *     content_id:string,
     *     is_inline:bool,
     *     content_base64:string
     *   }>
     * }
     */
    private function remoteMessagePayload(string $uid, string $subject): array
    {
        return [
            'uid' => $uid,
            'message_id' => '<'.$uid.'@example.test>',
            'folder' => 'INBOX',
            'from_email' => 'sender@example.test',
            'from_name' => 'Sender',
            'to_email' => 'receiver@example.test',
            'to_name' => 'Receiver',
            'subject' => $subject,
            'body_text' => 'Corpo '.$subject,
            'body_html' => '<p>Corpo '.$subject.'</p>',
            'snippet' => 'Snippet '.$subject,
            'received_at' => now()->toDateTimeString(),
            'is_seen' => false,
            'has_attachments' => true,
            'raw_headers' => ['header' => 'X-Test: 1'],
            'attachments' => [
                [
                    'filename' => 'anexo-'.$uid.'.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 1234,
                    'content_id' => 'cid-'.$uid,
                    'is_inline' => false,
                    'content_base64' => base64_encode('conteudo-'.$uid),
                ],
            ],
        ];
    }
}
