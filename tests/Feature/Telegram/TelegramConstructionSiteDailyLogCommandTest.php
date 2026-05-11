<?php

namespace Tests\Feature\Telegram;

use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteLog;
use App\Models\Customer;
use App\Models\TelegramPendingSelection;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Telegram\TelegramBotService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TelegramConstructionSiteDailyLogCommandTest extends TestCase
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

    public function test_direct_command_creates_daily_log_when_site_is_unique(): void
    {
        $company = $this->createCompany('Empresa Diario Telegram');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $site = $this->createConstructionSite($company, $user, 'Obra Telegram Unica');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $message, 'Registo diario criado')
                && str_contains((string) $message, (string) $site->code)
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/diario obra '.$site->code.' | Foram feitos trabalhos de taqueiro no piso 1.', 123456, 999001)
        )->assertOk();

        $this->assertDatabaseHas('construction_site_logs', [
            'company_id' => $company->id,
            'construction_site_id' => $site->id,
            'created_by' => $user->id,
            'title' => 'Registo via Telegram',
            'description' => 'Foram feitos trabalhos de taqueiro no piso 1.',
        ]);

        $this->assertDatabaseHas('telegram_pending_selections', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'type' => 'daily_log_attach_photos',
        ]);
    }

    public function test_command_does_not_use_other_company_site(): void
    {
        $companyA = $this->createCompany('Empresa Diario A');
        $companyB = $this->createCompany('Empresa Diario B');
        $userA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $userB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $this->createLink($companyA, $userA, 123456, '999001');
        $siteB = $this->createConstructionSite($companyB, $userB, 'Obra B Exclusiva');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao encontrei nenhuma obra para: '.$siteB->code);
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/diario obra '.$siteB->code.' | Teste de obra errada com descricao suficiente.', 123456, 999001)
        )->assertOk();

        $this->assertDatabaseMissing('construction_site_logs', [
            'company_id' => $companyA->id,
            'description' => 'Teste de obra errada com descricao suficiente.',
        ]);
    }

    public function test_multiple_sites_create_pending_selection(): void
    {
        $company = $this->createCompany('Empresa Diario Multi');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $this->createConstructionSite($company, $user, 'Obra XPTO Piso 0');
        $this->createConstructionSite($company, $user, 'Obra XPTO Piso 1');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $message, 'Encontrei varias obras:')
                && str_contains((string) $message, 'Responde com o numero.')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/diario obra XPTO | Aplicacao de verniz mate e revisao geral.', 123456, 999001)
        )->assertOk();

        $this->assertDatabaseHas('telegram_pending_selections', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'construction_site_daily_log_create',
        ]);
    }

    public function test_numeric_reply_creates_log_for_selected_site(): void
    {
        $company = $this->createCompany('Empresa Diario Numeric');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $site1 = $this->createConstructionSite($company, $user, 'Obra Alpha');
        $site2 = $this->createConstructionSite($company, $user, 'Obra Beta');

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'construction_site_daily_log_create',
            'payload' => [
                'ids' => [$site2->id, $site1->id],
                'description' => 'Descricao criada apos selecao numerica.',
            ],
            'expires_at' => now()->addMinutes(10),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $message, (string) $site2->code)
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('1', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseHas('construction_site_logs', [
            'company_id' => $company->id,
            'construction_site_id' => $site2->id,
            'description' => 'Descricao criada apos selecao numerica.',
        ]);
    }

    public function test_expired_selection_does_not_create_log(): void
    {
        $company = $this->createCompany('Empresa Diario Expirada');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $site = $this->createConstructionSite($company, $user, 'Obra Expirada');

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'construction_site_daily_log_create',
            'payload' => [
                'ids' => [$site->id],
                'description' => 'Descricao que nao deve ser criada.',
            ],
            'expires_at' => now()->subMinute(),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Selecao expirada. Faca o pedido novamente.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('1', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseMissing('construction_site_logs', [
            'company_id' => $company->id,
            'description' => 'Descricao que nao deve ser criada.',
        ]);
    }

    public function test_command_without_description_returns_usage_hint(): void
    {
        $company = $this->createCompany('Empresa Diario Sem Descricao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Use: /diario obra TERMO | DESCRICAO');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/diario obra XPTO', 123456, 999001)
        )->assertOk();
    }

    public function test_user_without_permission_cannot_create_daily_log(): void
    {
        $company = $this->createCompany('Empresa Diario Sem Permissao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $this->createLink($company, $user, 123456, '999001');
        $site = $this->createConstructionSite($company, $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN), 'Obra Bloqueada');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao tem permissao para criar registos de obra.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/diario obra '.$site->code.' | Tentativa sem permissao para criar registo.', 123456, 999001)
        )->assertOk();

        $this->assertDatabaseMissing('construction_site_logs', [
            'company_id' => $company->id,
            'description' => 'Tentativa sem permissao para criar registo.',
        ]);
    }

    public function test_photo_with_caption_creates_log_and_attaches_image(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Diario Foto');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $site = $this->createConstructionSite($company, $user, 'Obra Foto');

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=', true);
        $this->assertIsString($pngBytes);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('getFilePath')
            ->once()
            ->with('photo-file-1')
            ->andReturn('photos/test-file-1.png');
        $botMock->shouldReceive('downloadFileContents')
            ->once()
            ->with('photos/test-file-1.png')
            ->andReturn($pngBytes);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $message, 'Registo diario criado')
                && str_contains((string) $message, 'foto(s) anexada(s)')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayloadWithPhoto(
                '/diario obra '.$site->code.' | Aplicacao de acabamento e limpeza final.',
                123456,
                999001,
                'photo-file-1',
                1024
            )
        )->assertOk();

        $log = ConstructionSiteLog::query()
            ->forCompany((int) $company->id)
            ->where('construction_site_id', $site->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertDatabaseHas('construction_site_log_images', [
            'company_id' => $company->id,
            'construction_site_log_id' => $log->id,
            'mime_type' => 'image/png',
        ]);
    }

    public function test_additional_photo_is_attached_to_active_context_and_finish_stops_context(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Diario Attach');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $site = $this->createConstructionSite($company, $user, 'Obra Attach');

        $log = ConstructionSiteLog::query()->create([
            'company_id' => $company->id,
            'construction_site_id' => $site->id,
            'log_date' => now()->toDateString(),
            'type' => ConstructionSiteLog::TYPE_PROGRESS,
            'title' => 'Registo via Telegram',
            'description' => 'Registo inicial para anexar fotos depois.',
            'is_important' => false,
            'created_by' => $user->id,
        ]);

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'daily_log_attach_photos',
            'payload' => [
                'log_id' => $log->id,
                'construction_site_id' => $site->id,
            ],
            'expires_at' => now()->addMinutes(10),
        ]);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=', true);
        $this->assertIsString($pngBytes);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('getFilePath')
            ->once()
            ->with('photo-file-attach')
            ->andReturn('photos/attach.png');
        $botMock->shouldReceive('downloadFileContents')
            ->once()
            ->with('photos/attach.png')
            ->andReturn($pngBytes);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, '📎 Foto anexada ao registo diario.');
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Modo de anexar fotos terminado.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayloadWithPhoto('', 123456, 999001, 'photo-file-attach', 512)
        )->assertOk();

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('fim', 123456, 999001)
        )->assertOk();

        $this->assertDatabaseHas('construction_site_log_images', [
            'company_id' => $company->id,
            'construction_site_log_id' => $log->id,
            'mime_type' => 'image/png',
        ]);

        $this->assertDatabaseHas('telegram_pending_selections', [
            'company_id' => $company->id,
            'type' => 'daily_log_attach_photos',
        ]);
        $this->assertNotNull(TelegramPendingSelection::query()->latest('id')->first()?->consumed_at);
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

    private function createLink(Company $company, User $user, int $telegramUserId, string $chatId): TelegramUserLink
    {
        return TelegramUserLink::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => $telegramUserId,
            'telegram_chat_id' => $chatId,
            'is_active' => true,
            'linked_at' => now(),
            'last_seen_at' => now(),
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

    private function createConstructionSite(Company $company, User $creator, string $name): ConstructionSite
    {
        $customer = $this->createCustomer($company, $name.' Cliente');

        return ConstructionSite::createWithGeneratedCode((int) $company->id, [
            'name' => $name,
            'customer_id' => $customer->id,
            'status' => ConstructionSite::STATUS_IN_PROGRESS,
            'created_by' => $creator->id,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(string $text, int $fromId, int $chatId): array
    {
        return [
            'update_id' => random_int(1000, 9999),
            'message' => [
                'message_id' => random_int(10000, 99999),
                'from' => [
                    'id' => $fromId,
                    'is_bot' => false,
                    'first_name' => 'Tester',
                ],
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'text' => $text,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayloadWithPhoto(
        string $caption,
        int $fromId,
        int $chatId,
        string $fileId,
        int $fileSize
    ): array {
        return [
            'update_id' => random_int(1000, 9999),
            'message' => [
                'message_id' => random_int(10000, 99999),
                'from' => [
                    'id' => $fromId,
                    'is_bot' => false,
                    'first_name' => 'Tester',
                ],
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'date' => now()->timestamp,
                'caption' => $caption,
                'photo' => [
                    [
                        'file_id' => $fileId,
                        'file_size' => $fileSize,
                        'width' => 100,
                        'height' => 100,
                    ],
                ],
            ],
        ];
    }
}
