<?php

namespace Tests\Feature\Telegram;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\ProductFamily;
use App\Models\TelegramUserLink;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use App\Services\Telegram\TelegramBotService;

class TelegramStockCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InitialSaasSeeder::class);

        config()->set('telegram.enabled', true);
        config()->set('telegram.bot_token', 'test-bot-token');
        config()->set('telegram.webhook_secret', 'valid-secret');
        config()->set('telegram.allowed_user_ids', [123456]);
    }

    public function test_stock_without_linked_account_requires_link(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Conta Telegram nao ligada. Use /link CODIGO.');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/stock cabo', 123456, 999001)
        )->assertOk();
    }

    public function test_stock_without_term_returns_usage_hint(): void
    {
        $company = $this->createCompany('Empresa Stock Hint');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Use: /stock TERMO');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/stock', 123456, 999001)
        )->assertOk();
    }

    public function test_stock_finds_article_in_same_company(): void
    {
        $company = $this->createCompany('Empresa Stock Match');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $family = $this->createFamily($company, '30', 'Cabos');
        $article = $this->createArticle($company, $family, 'Cabo Azul MT', 12.5);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text) use ($article): bool {
                return (string) $chatId === '999001'
                    && str_contains((string) $text, 'Stock encontrado:')
                    && str_contains((string) $text, (string) $article->designation)
                    && str_contains((string) $text, (string) $article->code)
                    && str_contains((string) $text, 'Stock atual:');
            });
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/stock cabo', 123456, 999001)
        )->assertOk();
    }

    public function test_stock_does_not_show_articles_from_other_company(): void
    {
        $companyA = $this->createCompany('Empresa Stock A');
        $companyB = $this->createCompany('Empresa Stock B');
        $userA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $this->createLink($companyA, $userA, 123456, '999001');

        $familyB = $this->createFamily($companyB, '31', 'Cabos B');
        $this->createArticle($companyB, $familyB, 'Cabo Exclusivo B', 8);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao encontrei artigos para: cabo');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/stock cabo', 123456, 999001)
        )->assertOk();
    }

    public function test_stock_limits_results_to_five_items(): void
    {
        $company = $this->createCompany('Empresa Stock Limite');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $family = $this->createFamily($company, '32', 'Cabos Limite');
        for ($i = 1; $i <= 6; $i++) {
            $this->createArticle($company, $family, 'Cabo Resultado '.$i, (float) $i);
        }

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text): bool {
                return (string) $chatId === '999001'
                    && str_contains((string) $text, 'Mostrei os 5 primeiros resultados. Refine a pesquisa se necessario.')
                    && substr_count((string) $text, "\n1) ") === 1
                    && substr_count((string) $text, "\n5) ") === 1
                    && ! str_contains((string) $text, "\n6) ");
            });
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/stock cabo', 123456, 999001)
        )->assertOk();
    }

    public function test_stock_with_zero_results_returns_friendly_message(): void
    {
        $company = $this->createCompany('Empresa Stock Zero');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao encontrei artigos para: inexistente');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/stock inexistente', 123456, 999001)
        )->assertOk();
    }

    public function test_ping_and_id_commands_still_work_for_linked_user(): void
    {
        $company = $this->createCompany('Empresa Stock Regressao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')->once()->with(999001, 'pong');
        $mock->shouldReceive('sendMessage')->once()->with(999001, "Telegram user id: 123456\nChat id: 999001");
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/ping', 123456, 999001)
        )->assertOk();

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/id', 123456, 999001)
        )->assertOk();
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

    private function createFamily(Company $company, string $familyCode, string $name): ProductFamily
    {
        return ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => $name,
            'family_code' => $familyCode,
        ]);
    }

    private function createArticle(Company $company, ProductFamily $family, string $designation, float $stockQuantity): Article
    {
        $article = Article::createWithGeneratedCode($company->id, [
            'designation' => $designation,
            'product_family_id' => $family->id,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $this->mainland23Rate()->id,
            'moves_stock' => true,
            'stock_alert_enabled' => false,
            'is_active' => true,
        ]);

        $article->forceFill([
            'stock_quantity' => $stockQuantity,
        ])->save();

        return $article;
    }

    private function mainland23Rate(): VatRate
    {
        return VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 23%')
            ->firstOrFail();
    }

    private function defaultCategoryId(): int
    {
        return (int) Category::query()
            ->whereRaw('LOWER(name) = ?', ['produto'])
            ->value('id');
    }

    private function defaultUnitId(): int
    {
        return (int) Unit::query()
            ->where('code', 'UN')
            ->value('id');
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
