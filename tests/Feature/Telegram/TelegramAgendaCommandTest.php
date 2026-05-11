<?php

namespace Tests\Feature\Telegram;

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Telegram\TelegramAiIntentService;
use App\Services\Telegram\TelegramBotService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TelegramAgendaCommandTest extends TestCase
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

    public function test_agenda_without_linked_account_requires_link(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Conta Telegram nao ligada. Use /link CODIGO.');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/agenda hoje', 123456, 999001)
        )->assertOk();
    }

    public function test_agenda_without_argument_returns_usage_hint(): void
    {
        $company = $this->createCompany('Empresa Agenda Hint');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Use: /agenda hoje, /agenda amanha, /agenda semana ou /agenda mes');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/agenda', 123456, 999001)
        )->assertOk();
    }

    public function test_agenda_hoje_shows_only_today_events_from_same_company(): void
    {
        $companyA = $this->createCompany('Empresa Agenda Hoje A');
        $companyB = $this->createCompany('Empresa Agenda Hoje B');
        $userA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $userB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $this->createLink($companyA, $userA, 123456, '999001');

        $today = now()->startOfDay();
        $this->createEvent($companyA, $userA, 'Visita obra Maia', $today->copy()->addHours(9), CalendarEvent::STATUS_PENDING);
        $this->createEvent($companyB, $userB, 'Evento outra empresa', $today->copy()->addHours(10), CalendarEvent::STATUS_PENDING);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text): bool {
                return (string) $chatId === '999001'
                    && str_contains((string) $text, 'Agenda de hoje:')
                    && str_contains((string) $text, '09:00 - Visita obra Maia')
                    && ! str_contains((string) $text, 'Evento outra empresa');
            });
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/agenda hoje', 123456, 999001)
        )->assertOk();
    }

    public function test_agenda_amanha_and_amanha_with_accent_are_supported(): void
    {
        $company = $this->createCompany('Empresa Agenda Amanha');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $tomorrow = now()->addDay()->startOfDay()->addHours(11);
        $this->createEvent($company, $user, 'Reuniao cliente Porto', $tomorrow, CalendarEvent::STATUS_PENDING);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool => (string) $chatId === '999001' && str_contains((string) $text, '11:00 - Reuniao cliente Porto'));
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool => (string) $chatId === '999001' && str_contains((string) $text, '11:00 - Reuniao cliente Porto'));
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/agenda amanha', 123456, 999001))
            ->assertOk();
        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/agenda amanhã', 123456, 999001))
            ->assertOk();
    }

    public function test_agenda_semana_uses_current_week_range(): void
    {
        $company = $this->createCompany('Empresa Agenda Semana');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $weekStart = now()->startOfWeek()->startOfDay();
        $this->createEvent($company, $user, 'Evento semana atual', $weekStart->copy()->addDays(2)->addHours(10), CalendarEvent::STATUS_PENDING);
        $this->createEvent($company, $user, 'Evento fora da semana', $weekStart->copy()->addWeeks(2)->addHours(10), CalendarEvent::STATUS_PENDING);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text): bool {
                return (string) $chatId === '999001'
                    && str_contains((string) $text, 'Agenda de esta semana:')
                    && str_contains((string) $text, 'Evento semana atual')
                    && ! str_contains((string) $text, 'Evento fora da semana');
            });
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/agenda semana', 123456, 999001)
        )->assertOk();
    }

    public function test_agenda_does_not_show_cancelled_events(): void
    {
        $company = $this->createCompany('Empresa Agenda Cancelados');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $today = now()->startOfDay();
        $this->createEvent($company, $user, 'Evento ativo', $today->copy()->addHours(8), CalendarEvent::STATUS_PENDING);
        $this->createEvent($company, $user, 'Evento cancelado', $today->copy()->addHours(9), CalendarEvent::STATUS_CANCELLED);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text): bool {
                return (string) $chatId === '999001'
                    && str_contains((string) $text, 'Evento ativo')
                    && ! str_contains((string) $text, 'Evento cancelado');
            });
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/agenda hoje', 123456, 999001)
        )->assertOk();
    }

    public function test_agenda_applies_results_limit(): void
    {
        $company = $this->createCompany('Empresa Agenda Limite');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $today = now()->startOfDay();
        for ($i = 1; $i <= 12; $i++) {
            $this->createEvent(
                $company,
                $user,
                'Evento '.$i,
                $today->copy()->addHours(7)->addMinutes($i),
                CalendarEvent::STATUS_PENDING
            );
        }

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text): bool {
                return (string) $chatId === '999001'
                    && str_contains((string) $text, 'Evento 10')
                    && ! str_contains((string) $text, 'Evento 11')
                    && ! str_contains((string) $text, 'Evento 12');
            });
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/agenda hoje', 123456, 999001)
        )->assertOk();
    }

    public function test_natural_agenda_question_returns_structured_summary_and_skips_ai(): void
    {
        $company = $this->createCompany('Empresa Agenda Natural Query');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $tomorrow = now()->addDay()->startOfDay()->addHours(10);
        $this->createEvent($company, $user, 'Visita obra Maia', $tomorrow, CalendarEvent::STATUS_PENDING);

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldNotReceive('detect');
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Agenda de amanha:')
                && str_contains((string) $text, '10:00 - Visita obra Maia')
            );
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('qual a agenda para amanha?', 123456, 999001)
        )->assertOk();
    }

    public function test_natural_agenda_question_for_weekday_returns_that_weekday(): void
    {
        $company = $this->createCompany('Empresa Agenda Natural Segunda');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $nextMonday = now()->next('Monday')->startOfDay()->addHours(9);
        $this->createEvent($company, $user, 'Reuniao de segunda', $nextMonday, CalendarEvent::STATUS_PENDING);
        $this->createEvent($company, $user, 'Evento de hoje', now()->startOfDay()->addHours(10), CalendarEvent::STATUS_PENDING);

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Agenda de segunda:')
                && str_contains((string) $text, '09:00 - Reuniao de segunda')
                && ! str_contains((string) $text, 'Evento de hoje')
            );
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('qual a agenda para segunda?', 123456, 999001)
        )->assertOk();
    }

    public function test_existing_commands_still_work(): void
    {
        $company = $this->createCompany('Empresa Agenda Regressao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')->once()->with(999001, 'pong');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/ping', 123456, 999001)
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

    private function createEvent(
        Company $company,
        User $user,
        string $title,
        \DateTimeInterface $startsAt,
        string $status
    ): CalendarEvent {
        return CalendarEvent::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => $title,
            'type' => CalendarEvent::TYPE_TASK,
            'status' => $status,
            'priority' => CalendarEvent::PRIORITY_NORMAL,
            'starts_at' => $startsAt,
            'ends_at' => null,
            'all_day' => false,
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
