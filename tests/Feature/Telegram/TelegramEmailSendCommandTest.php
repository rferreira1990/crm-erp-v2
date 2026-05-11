<?php

namespace Tests\Feature\Telegram;

use App\DTO\Telegram\TelegramAiIntentData;
use App\Models\Company;
use App\Models\TelegramEmailDraft;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Ai\EmailTextImproverService;
use App\Services\Telegram\TelegramAiIntentService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramEmailAttachmentService;
use App\Services\Telegram\TelegramEmailSendService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TelegramEmailSendCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InitialSaasSeeder::class);

        config()->set('telegram.enabled', true);
        config()->set('telegram.bot_token', 'test-bot-token');
        config()->set('telegram.webhook_secret', 'valid-secret');
        config()->set('telegram.allowed_user_ids', []);
        config()->set('ai.enabled', true);
    }

    public function test_email_command_without_link_prompts_for_link(): void
    {
        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Conta Telegram nao ligada. Use /link CODIGO.');
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/email teste@example.com'))
            ->assertOk();
    }

    public function test_email_command_with_invalid_email_returns_friendly_message(): void
    {
        [$company, $user] = $this->companyAndAdmin('Empresa Email Invalido');
        $this->createLink($company, $user);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Para iniciar, use: /email destinatario@dominio.pt');
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/email invalido'))
            ->assertOk();
    }

    public function test_email_command_creates_draft_and_asks_subject(): void
    {
        [$company, $user] = $this->companyAndAdmin('Empresa Email Draft');
        $this->createLink($company, $user);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Passo 1/4'));
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/email xpto@exemplo.pt'))
            ->assertOk();

        $this->assertDatabaseHas('telegram_email_drafts', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'to_email' => 'xpto@exemplo.pt',
            'status' => TelegramEmailDraft::STATUS_COLLECTING_SUBJECT,
        ]);
    }

    public function test_email_draft_flow_to_preview_without_ai(): void
    {
        [$company, $user] = $this->companyAndAdmin('Empresa Email Flow');
        $this->createLink($company, $user);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')->once()->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Passo 1/4'));
        $bot->shouldReceive('sendMessage')->once()->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Passo 2/4'));
        $bot->shouldReceive('sendMessage')->once()->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Passo 3/4'));
        $bot->shouldReceive('sendMessage')->once()->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Passo 4/4'));
        $bot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Resumo para confirmacao') && str_contains((string) $text, 'OK ENVIAR'));
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/email xpto@exemplo.pt'))->assertOk();
        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('Assunto de teste'))->assertOk();
        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('Texto base para envio.'))->assertOk();
        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('sem anexos'))->assertOk();
        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('NAO'))->assertOk();

        $this->assertDatabaseHas('telegram_email_drafts', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => TelegramEmailDraft::STATUS_AWAITING_FINAL_APPROVAL,
            'subject' => 'Assunto de teste',
        ]);
    }

    public function test_email_body_keeps_line_breaks_from_telegram_message(): void
    {
        [$company, $user] = $this->companyAndAdmin('Empresa Email Quebras Linha');
        $this->createLink($company, $user);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')->once()->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Passo 1/4'));
        $bot->shouldReceive('sendMessage')->once()->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Passo 2/4'));
        $bot->shouldReceive('sendMessage')->once()->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Passo 3/4'));
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/email xpto@exemplo.pt'))->assertOk();
        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('Assunto com formato'))->assertOk();

        $body = "Boa tarde\n\nConforme combinado, segue anexo.\n\nCumprimentos\nRicardo";
        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload($body))->assertOk();

        /** @var TelegramEmailDraft $draft */
        $draft = TelegramEmailDraft::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($body, $draft->original_body);
        $this->assertSame($body, $draft->selected_body);
    }

    public function test_ok_enviar_calls_send_service_and_marks_draft_sent(): void
    {
        [$company, $user] = $this->companyAndAdmin('Empresa Email Send');
        $this->createLink($company, $user);

        $draft = TelegramEmailDraft::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'status' => TelegramEmailDraft::STATUS_AWAITING_FINAL_APPROVAL,
            'to_email' => 'xpto@exemplo.pt',
            'subject' => 'Assunto',
            'original_body' => 'Texto',
            'selected_body' => 'Texto',
            'attachments' => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        $sendService = Mockery::mock(TelegramEmailSendService::class);
        $sendService->shouldReceive('send')
            ->once()
            ->withArgs(fn (TelegramEmailDraft $given) => (int) $given->id === (int) $draft->id)
            ->andReturn(['success' => true, 'message' => 'Email enviado com sucesso.']);
        $this->app->instance(TelegramEmailSendService::class, $sendService);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')->once()->with(999001, 'Email enviado com sucesso.');
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('OK ENVIAR'))
            ->assertOk();

        $draft->refresh();
        $this->assertSame(TelegramEmailDraft::STATUS_SENT, $draft->status);
        $this->assertNotNull($draft->sent_at);
    }

    public function test_email_is_not_sent_without_ok_enviar(): void
    {
        [$company, $user] = $this->companyAndAdmin('Empresa Email Sem OK');
        $this->createLink($company, $user);

        TelegramEmailDraft::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'status' => TelegramEmailDraft::STATUS_AWAITING_FINAL_APPROVAL,
            'to_email' => 'xpto@exemplo.pt',
            'subject' => 'Assunto',
            'original_body' => 'Texto',
            'selected_body' => 'Texto',
            'attachments' => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        $sendService = Mockery::mock(TelegramEmailSendService::class);
        $sendService->shouldNotReceive('send');
        $this->app->instance(TelegramEmailSendService::class, $sendService);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')->once()->with(999001, 'Para concluir, responda OK ENVIAR. Para desistir, responda CANCELAR.');
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('enviar agora'))
            ->assertOk();
    }

    public function test_ai_improvement_yes_uses_email_text_improver(): void
    {
        [$company, $user] = $this->companyAndAdmin('Empresa Email AI Improve');
        $this->createLink($company, $user);

        TelegramEmailDraft::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'status' => TelegramEmailDraft::STATUS_AI_IMPROVEMENT_OFFER,
            'to_email' => 'xpto@exemplo.pt',
            'subject' => 'Assunto',
            'original_body' => 'texto original',
            'selected_body' => 'texto original',
            'attachments' => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        $improver = Mockery::mock(EmailTextImproverService::class);
        $improver->shouldReceive('improve')
            ->once()
            ->andReturn([
                'improved_text' => 'texto melhorado',
                'model' => 'gpt-5.4-nano',
                'input_tokens' => 10,
                'output_tokens' => 20,
                'total_tokens' => 30,
                'estimated_cost_eur' => 0.0001,
            ]);
        $this->app->instance(EmailTextImproverService::class, $improver);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Versao melhorada sugerida:') && str_contains((string) $text, 'texto melhorado'));
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('SIM'))->assertOk();

        $this->assertDatabaseHas('telegram_email_drafts', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => TelegramEmailDraft::STATUS_AI_IMPROVEMENT_PREVIEW,
            'improved_body' => 'texto melhorado',
        ]);
    }

    public function test_attachment_rejection_returns_friendly_message(): void
    {
        [$company, $user] = $this->companyAndAdmin('Empresa Email Attach Reject');
        $this->createLink($company, $user);

        TelegramEmailDraft::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'status' => TelegramEmailDraft::STATUS_COLLECTING_ATTACHMENTS,
            'to_email' => 'xpto@exemplo.pt',
            'subject' => 'Assunto',
            'original_body' => 'texto original',
            'selected_body' => 'texto original',
            'attachments' => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        $attachmentService = Mockery::mock(TelegramEmailAttachmentService::class);
        $attachmentService->shouldReceive('handleIncomingAttachment')
            ->once()
            ->andReturn([
                'accepted' => false,
                'reason' => 'Tipo de ficheiro nao permitido.',
            ]);
        $this->app->instance(TelegramEmailAttachmentService::class, $attachmentService);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')->once()->with(999001, 'Tipo de ficheiro nao permitido.');
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayloadWithDocument('file-id-1'))
            ->assertOk();
    }

    public function test_natural_language_email_request_starts_flow(): void
    {
        [$company, $user] = $this->companyAndAdmin('Empresa Email AI Start');
        $link = $this->createLink($company, $user);

        $intentService = Mockery::mock(TelegramAiIntentService::class);
        $intentService->shouldReceive('detect')
            ->once()
            ->withArgs(fn ($text, TelegramUserLink $givenLink) => $text === 'enviar email para xpto@exemplo.pt' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_SEND_EMAIL_START,
                term: 'xpto@exemplo.pt',
                confidence: 0.97
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentService);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text) => (string) $chatId === '999001' && str_contains((string) $text, 'Passo 1/4'));
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('enviar email para xpto@exemplo.pt'))
            ->assertOk();

        $this->assertDatabaseHas('telegram_email_drafts', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'to_email' => 'xpto@exemplo.pt',
            'status' => TelegramEmailDraft::STATUS_COLLECTING_SUBJECT,
        ]);
    }

    private function companyAndAdmin(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => true,
            'email' => Str::lower(Str::random(8)).'@example.test',
        ]);

        $user->syncRoles([User::ROLE_COMPANY_ADMIN]);

        return [$company, $user];
    }

    private function createLink(Company $company, User $user): TelegramUserLink
    {
        return TelegramUserLink::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'is_active' => true,
            'linked_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(string $text): array
    {
        return [
            'update_id' => 100001,
            'message' => [
                'message_id' => 4501,
                'from' => [
                    'id' => 123456,
                    'is_bot' => false,
                    'first_name' => 'Tester',
                ],
                'chat' => [
                    'id' => 999001,
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'text' => $text,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function messagePayloadWithDocument(string $fileId): array
    {
        return [
            'update_id' => 100001,
            'message' => [
                'message_id' => 4501,
                'from' => [
                    'id' => 123456,
                    'is_bot' => false,
                    'first_name' => 'Tester',
                ],
                'chat' => [
                    'id' => 999001,
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'document' => [
                    'file_id' => $fileId,
                    'file_name' => 'teste.exe',
                    'mime_type' => 'application/octet-stream',
                    'file_size' => 1024,
                ],
            ],
        ];
    }
}
