<?php

namespace Tests\Feature\Telegram;

use App\Models\Company;
use App\Models\TelegramLinkCode;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramLinkCodeService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TelegramLinkTest extends TestCase
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
    }

    public function test_company_admin_can_open_telegram_link_page(): void
    {
        $company = $this->createCompany('Empresa Link Admin');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.telegram.link.index'));

        $response->assertOk();
        $response->assertSee('Ligacao Telegram');
    }

    public function test_company_user_without_permission_cannot_open_link_page(): void
    {
        $company = $this->createCompany('Empresa Link User');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $this->actingAs($user)
            ->get(route('admin.telegram.link.index'))
            ->assertForbidden();
    }

    public function test_generate_code_route_creates_telegram_link_code(): void
    {
        $company = $this->createCompany('Empresa Gerar Codigo');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $this->actingAs($admin)
            ->post(route('admin.telegram.link.code'))
            ->assertRedirect(route('admin.telegram.link.index'));

        $code = TelegramLinkCode::query()
            ->forCompany((int) $company->id)
            ->where('user_id', (int) $admin->id)
            ->first();

        $this->assertNotNull($code);
        $this->assertTrue($code->expires_at !== null);
        $this->assertEquals(
            now()->addMinutes(10)->format('Y-m-d H:i'),
            $code->expires_at->format('Y-m-d H:i')
        );
    }

    public function test_link_command_with_valid_code_creates_telegram_user_link(): void
    {
        $company = $this->createCompany('Empresa Link Valido');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $code = app(TelegramLinkCodeService::class)->generateForUser($admin);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text) use ($admin, $company): bool {
                return (string) $chatId === '999001'
                    && str_contains((string) $text, 'Telegram ligado com sucesso')
                    && str_contains((string) $text, $admin->name)
                    && str_contains((string) $text, $company->name);
            });
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/link '.$code->code, 321654, 999001)
        )->assertOk();

        $this->assertDatabaseHas('telegram_user_links', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'telegram_user_id' => 321654,
            'is_active' => 1,
        ]);
    }

    public function test_link_command_with_invalid_code_returns_friendly_error(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Codigo de ligacao invalido.');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/link ABC123', 321654, 999001)
        )->assertOk();
    }

    public function test_link_command_with_expired_code_returns_friendly_error(): void
    {
        $company = $this->createCompany('Empresa Link Expirado');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $code = app(TelegramLinkCodeService::class)->generateForUser($admin);

        $code->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Codigo de ligacao expirado.');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/link '.$code->code, 321654, 999001)
        )->assertOk();
    }

    public function test_link_command_does_not_duplicate_when_telegram_user_is_already_linked(): void
    {
        $company = $this->createCompany('Empresa Link Duplicado');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        TelegramUserLink::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'telegram_user_id' => 321654,
            'telegram_chat_id' => '999001',
            'is_active' => true,
            'linked_at' => now(),
            'last_seen_at' => now(),
        ]);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Esta conta Telegram ja esta ligada ao ERP.');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/link TESTE01', 321654, 999001)
        )->assertOk();

        $this->assertSame(
            1,
            TelegramUserLink::query()->where('telegram_user_id', 321654)->count()
        );
    }

    public function test_message_from_linked_user_updates_last_seen_and_chat_id(): void
    {
        $company = $this->createCompany('Empresa Last Seen');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $link = TelegramUserLink::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'telegram_user_id' => 321654,
            'telegram_chat_id' => '1000',
            'is_active' => true,
            'linked_at' => now()->subHour(),
            'last_seen_at' => now()->subHour(),
        ]);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'pong');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/ping', 321654, 999001)
        )->assertOk();

        $link->refresh();

        $this->assertSame('999001', $link->telegram_chat_id);
        $this->assertTrue($link->last_seen_at !== null && $link->last_seen_at->gt(now()->subMinutes(2)));
    }

    public function test_link_command_ignores_company_id_from_payload_and_uses_code_owner_company(): void
    {
        $companyA = $this->createCompany('Empresa Payload A');
        $companyB = $this->createCompany('Empresa Payload B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $code = app(TelegramLinkCodeService::class)->generateForUser($adminA);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')->once();
        $this->app->instance(TelegramBotService::class, $mock);

        $payload = $this->messagePayload('/link '.$code->code, 789456, 999001);
        $payload['company_id'] = $companyB->id;
        $payload['message']['company_id'] = $companyB->id;

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $payload)
            ->assertOk();

        $this->assertDatabaseHas('telegram_user_links', [
            'telegram_user_id' => 789456,
            'company_id' => $companyA->id,
            'user_id' => $adminA->id,
        ]);
    }

    public function test_link_page_does_not_expose_links_of_other_companies(): void
    {
        $companyA = $this->createCompany('Empresa Scope A');
        $companyB = $this->createCompany('Empresa Scope B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        TelegramUserLink::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $adminB->id,
            'telegram_user_id' => 112233,
            'telegram_chat_id' => '445566',
            'is_active' => true,
            'linked_at' => now(),
            'last_seen_at' => now(),
        ]);

        $response = $this->actingAs($adminA)->get(route('admin.telegram.link.index'));

        $response->assertOk();
        $response->assertDontSee('112233');
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
