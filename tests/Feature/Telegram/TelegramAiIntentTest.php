<?php

namespace Tests\Feature\Telegram;

use App\DTO\Ai\AiResponseData;
use App\DTO\Telegram\TelegramAiIntentData;
use App\Models\AiUsageLog;
use App\Models\Company;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Ai\AiExecutionService;
use App\Services\Telegram\Commands\TelegramConstructionSiteDailyLogCommandService;
use App\Services\Telegram\Commands\TelegramStockCommandService;
use App\Services\Telegram\TelegramAiIntentService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramLinkCodeService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TelegramAiIntentTest extends TestCase
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
        config()->set('ai.monthly_budget_eur', null);
    }

    public function test_natural_stock_text_calls_ai_intent_and_then_stock_service(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Stock');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'quanto stock tenho de cabo 3g2.5?' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_STOCK_LOOKUP,
                term: 'cabo 3g2.5',
                confidence: 0.95
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $stockMock = Mockery::mock(TelegramStockCommandService::class);
        $stockMock->shouldReceive('execute')
            ->once()
            ->withArgs(fn (TelegramUserLink $givenLink, string $term): bool => $givenLink->id === $link->id && $term === 'cabo 3g2.5')
            ->andReturn('Stock encontrado: ...');
        $this->app->instance(TelegramStockCommandService::class, $stockMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Stock encontrado: ...');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('quanto stock tenho de cabo 3g2.5?', 123456, 999001))
            ->assertOk();
    }

    public function test_natural_daily_log_text_calls_daily_log_command_service(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Diario');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'criar registo diario na obra XPTO, foram feitos trabalhos de taqueiro' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CREATE_CONSTRUCTION_SITE_DAILY_LOG,
                term: null,
                confidence: 0.91,
                siteTerm: 'XPTO',
                description: 'Foram feitos trabalhos de taqueiro no piso 1.'
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $dailyLogMock = Mockery::mock(TelegramConstructionSiteDailyLogCommandService::class);
        $dailyLogMock->shouldReceive('execute')
            ->once()
            ->withArgs(function (TelegramUserLink $givenLink, int $chatId, string $siteTerm, string $description, array $images): bool {
                return $givenLink->id > 0
                    && $chatId === 999001
                    && $siteTerm === 'XPTO'
                    && $description === 'Foram feitos trabalhos de taqueiro no piso 1.'
                    && $images === [];
            })
            ->andReturn([
                'status' => 'created',
                'message' => '✅ Registo diario criado na obra OBR-2026-0001 — Casa XPTO.',
                'created' => true,
                'log_id' => 10,
                'site_id' => 5,
            ]);
        $this->app->instance(TelegramConstructionSiteDailyLogCommandService::class, $dailyLogMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, '✅ Registo diario criado na obra OBR-2026-0001 — Casa XPTO.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('criar registo diario na obra XPTO, foram feitos trabalhos de taqueiro', 123456, 999001)
        )->assertOk();
    }

    public function test_stock_command_keeps_working_without_ai_call(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Slash Stock');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldNotReceive('detect');
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $stockMock = Mockery::mock(TelegramStockCommandService::class);
        $stockMock->shouldReceive('execute')
            ->once()
            ->withArgs(fn (TelegramUserLink $givenLink, string $term): bool => $givenLink->id === $link->id && $term === 'cabo')
            ->andReturn('Stock encontrado: /stock');
        $this->app->instance(TelegramStockCommandService::class, $stockMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Stock encontrado: /stock');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/stock cabo', 123456, 999001))
            ->assertOk();
    }

    public function test_unlinked_user_does_not_call_ai_and_gets_link_message(): void
    {
        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldNotReceive('detect');
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Conta Telegram nao ligada. Use /link CODIGO.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('ola', 123456, 999001))
            ->assertOk();
    }

    public function test_unknown_intent_returns_help_message(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Unknown');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'ola bot' && $givenLink->id === $link->id)
            ->andReturn(TelegramAiIntentData::unknown());
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $stockMock = Mockery::mock(TelegramStockCommandService::class);
        $stockMock->shouldNotReceive('execute');
        $this->app->instance(TelegramStockCommandService::class, $stockMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Posso ajudar com stock, orcamentos pendentes, informacao de orcamentos, saldos, registos diarios de obra e envio de email.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('ola bot', 123456, 999001))
            ->assertOk();
    }

    public function test_budget_exceeded_blocks_before_ai(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Budget Exceeded', [
            'ai_monthly_budget_eur' => 1.00,
            'ai_budget_warning_percent' => 80,
            'ai_budget_hard_stop_enabled' => true,
        ]);
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $this->createUsageLog($company, 1.05);

        $executionMock = Mockery::mock(AiExecutionService::class);
        $executionMock->shouldNotReceive('executePrompt');
        $this->app->instance(AiExecutionService::class, $executionMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Limite mensal de AI atingido para esta empresa.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('quanto stock de cabo?', 123456, 999001))
            ->assertOk();
    }

    public function test_invalid_ai_json_falls_back_to_unknown_and_logs_usage(): void
    {
        $company = $this->createCompany('Empresa Telegram AI JSON');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $executionMock = Mockery::mock(AiExecutionService::class);
        $executionMock->shouldReceive('executePrompt')
            ->once()
            ->andReturn(new AiResponseData(
                text: 'resposta sem json',
                model: 'gpt-5.4-nano',
                inputTokens: 12,
                outputTokens: 7,
                totalTokens: 19,
                estimatedCostEur: 0.000012
            ));
        $this->app->instance(AiExecutionService::class, $executionMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Posso ajudar com stock, orcamentos pendentes, informacao de orcamentos, saldos, registos diarios de obra e envio de email.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('ola bom dia', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseHas('ai_usage_logs', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'source' => 'telegram_ai_intent',
            'model' => 'gpt-5.4-nano',
        ]);
    }

    public function test_ai_failure_returns_friendly_message(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Exception');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->andThrow(new RuntimeException('OpenAI timeout'));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao consegui interpretar o pedido agora. Tente novamente.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('quero saber stock', 123456, 999001))
            ->assertOk();
    }

    public function test_ping_id_and_link_commands_still_work(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Regressao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $linkCode = app(TelegramLinkCodeService::class)->generateForUser($user);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')->once()->with(999001, 'pong');
        $botMock->shouldReceive('sendMessage')->once()->with(999001, "Telegram user id: 123456\nChat id: 999001");
        $botMock->shouldReceive('sendMessage')->once()->with(999002, 'Telegram ligado com sucesso ao utilizador '.$user->name.' / empresa '.$company->name.'.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/ping', 123456, 999001))
            ->assertOk();
        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/id', 123456, 999001))
            ->assertOk();

        $this->deleteExistingLink(123457);
        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/link '.$linkCode->code, 123457, 999002))
            ->assertOk();
    }

    public function test_stock_like_command_with_suffix_is_not_treated_as_stock_command(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Hardening');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldNotReceive('detect');
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $stockMock = Mockery::mock(TelegramStockCommandService::class);
        $stockMock->shouldNotReceive('execute');
        $this->app->instance(TelegramStockCommandService::class, $stockMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Bot Telegram ligado. AI ainda nao esta ativa nesta fase.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/stockxxx cabo', 123456, 999001))
            ->assertOk();
    }

    private function createCompany(string $name, array $overrides = []): Company
    {
        return Company::query()->create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ], $overrides));
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

    private function deleteExistingLink(int $telegramUserId): void
    {
        TelegramUserLink::query()->where('telegram_user_id', $telegramUserId)->delete();
    }

    private function createUsageLog(Company $company, float $cost): AiUsageLog
    {
        return AiUsageLog::query()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'source' => 'telegram_ai_intent',
            'model' => 'gpt-5.4-nano',
            'input_tokens' => 1,
            'output_tokens' => 1,
            'total_tokens' => 2,
            'estimated_cost_eur' => $cost,
            'metadata' => null,
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
}
