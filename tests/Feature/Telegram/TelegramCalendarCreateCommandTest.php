<?php

namespace Tests\Feature\Telegram;

use App\DTO\Ai\AiResponseData;
use App\DTO\Telegram\TelegramAiIntentData;
use App\Exceptions\Ai\AiBudgetExceededException;
use App\Models\AiUsageLog;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\Customer;
use App\Models\TelegramPendingSelection;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Ai\AiExecutionService;
use App\Services\Calendar\CalendarEventService;
use App\Services\Telegram\TelegramAiIntentService;
use App\Services\Telegram\TelegramBotService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TelegramCalendarCreateCommandTest extends TestCase
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

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('marca visita amanha as 10h na obra Maia', 123456, 999001))
            ->assertOk();
    }

    public function test_natural_calendar_create_creates_pending_preview(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Draft');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');
        $site = $this->createConstructionSite($company, $user, 'Obra Maia');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'marca visita amanha as 10h na obra Maia' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.95,
                data: [
                    'title' => 'Visita obra Maia',
                    'description' => null,
                    'type' => 'visita',
                    'date_text' => 'amanha',
                    'time_text' => '10h',
                    'construction_site_term' => (string) $site->code,
                    'priority' => 'normal',
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, 'Vou criar este evento:')
                && str_contains((string) $message, 'OK CRIAR')
                && str_contains((string) $message, 'CANCELAR'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('marca visita amanha as 10h na obra Maia', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseHas('telegram_pending_selections', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'calendar_event_create',
        ]);
    }

    public function test_ok_criar_creates_calendar_event(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Confirm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'calendar_event_create',
            'payload' => [
                'calendar_event' => [
                    'title' => 'Visita tecnica',
                    'description' => 'Visita criada por telegram',
                    'type' => CalendarEvent::TYPE_VISIT,
                    'status' => CalendarEvent::STATUS_PENDING,
                    'priority' => CalendarEvent::PRIORITY_NORMAL,
                    'starts_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
                    'ends_at' => now()->addDay()->setTime(11, 0)->toDateTimeString(),
                    'all_day' => false,
                    'user_id' => $user->id,
                    'customer_id' => null,
                    'supplier_id' => null,
                    'construction_site_id' => null,
                    'quote_id' => null,
                ],
            ],
            'expires_at' => now()->addMinutes(10),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001' && str_contains((string) $message, 'Evento criado com sucesso.'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('OK CRIAR', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseHas('calendar_events', [
            'company_id' => $company->id,
            'title' => 'Visita tecnica',
            'created_by' => $user->id,
        ]);
    }

    public function test_cancelar_does_not_create_event(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Cancelar');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'calendar_event_create',
            'payload' => ['calendar_event' => []],
            'expires_at' => now()->addMinutes(10),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Criacao cancelada.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('CANCELAR', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseCount('calendar_events', 0);
    }

    public function test_expired_pending_does_not_create_event(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Expirada');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'calendar_event_create',
            'payload' => ['calendar_event' => []],
            'expires_at' => now()->subMinute(),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao existe criacao pendente. Envie um novo pedido de agenda.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('OK CRIAR', 123456, 999001))
            ->assertOk();
    }

    public function test_missing_date_requests_more_data(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Missing');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'marca visita para ir a obra maia' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.80,
                data: [
                    'title' => 'Visita obra Maia',
                    'type' => 'visita',
                    'date_text' => null,
                    'time_text' => null,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Faltam dados. Indique data e hora no pedido (ex.: "amanha as 10h").');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('marca visita para ir a obra maia', 123456, 999001))
            ->assertOk();
    }

    public function test_proxima_terca_dia_todo_is_supported(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Dia Todo');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');
        $site = $this->createConstructionSite($company, $user, 'Obra Maia');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'agendar obra do sr vitor na maia para proxima terca feira o dia todo' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.95,
                data: [
                    'title' => 'Visita obra Maia',
                    'type' => 'obra',
                    'date_text' => 'proxima terca feira',
                    'time_text' => null,
                    'construction_site_term' => (string) $site->code,
                    'all_day' => true,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, '(todo o dia)'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('agendar obra do sr vitor na maia para proxima terca feira o dia todo', 123456, 999001))
            ->assertOk();

        $selection = TelegramPendingSelection::query()
            ->where('company_id', $company->id)
            ->where('type', 'calendar_event_create')
            ->latest('id')
            ->first();

        $this->assertNotNull($selection);
        $payload = is_array($selection->payload) ? $selection->payload : [];
        $calendarEvent = is_array($payload['calendar_event'] ?? null) ? $payload['calendar_event'] : [];
        $this->assertTrue((bool) ($calendarEvent['all_day'] ?? false));
    }

    public function test_date_with_time_like_ligar_ao_luis_is_supported(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Ligar');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'ligar ao luis ( 9145245641) por causa disto as 18h de dia 5/06' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.91,
                data: [
                    'title' => 'Ligar ao Luis',
                    'type' => 'tarefa',
                    'date_text' => null,
                    'time_text' => null,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, '18:00'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('ligar ao luis ( 9145245641) por causa disto as 18h de dia 5/06', 123456, 999001))
            ->assertOk();
    }

    public function test_comprar_pao_amanha_creates_all_day_preview(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Pao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'comprar pao amanha' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.90,
                data: [
                    'title' => 'Comprar pao',
                    'type' => 'tarefa',
                    'date_text' => null,
                    'time_text' => null,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, '(todo o dia)'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('comprar pao amanha', 123456, 999001))
            ->assertOk();
    }

    public function test_depois_do_almoco_phrase_is_mapped_to_default_time(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Almoco');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'agendar para amanha depois do almoco ir a gaia a sanipower' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.82,
                data: [
                    'title' => 'Visita',
                    'type' => 'visita',
                    'date_text' => null,
                    'time_text' => null,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, '14:00'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('agendar para amanha depois do almoco ir a gaia a sanipower', 123456, 999001))
            ->assertOk();
    }

    public function test_fim_da_tarde_phrase_is_mapped_to_18h(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Fim Tarde');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'marca reuniao sexta ao fim da tarde' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.88,
                data: [
                    'title' => 'Reuniao',
                    'type' => 'reuniao',
                    'date_text' => null,
                    'time_text' => null,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, '18:00'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('marca reuniao sexta ao fim da tarde', 123456, 999001))
            ->assertOk();
    }

    public function test_daqui_a_2_dias_is_supported(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Daqui');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'cria tarefa daqui a 2 dias as 9h' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.91,
                data: [
                    'title' => 'Tarefa',
                    'type' => 'tarefa',
                    'date_text' => null,
                    'time_text' => null,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $expected = now()->addDays(2)->format('d/m/Y');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, $expected)
                && str_contains((string) $message, '09:00'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('cria tarefa daqui a 2 dias as 9h', 123456, 999001))
            ->assertOk();
    }

    public function test_time_in_words_like_oito_e_meia_is_supported(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Oito Meia');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'marca visita amanha as 8 e meia' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.90,
                data: [
                    'title' => 'Visita',
                    'type' => 'visita',
                    'date_text' => null,
                    'time_text' => null,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, '08:30'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('marca visita amanha as 8 e meia', 123456, 999001))
            ->assertOk();
    }

    public function test_time_range_format_is_parsed_from_ai_fields(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Range');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'segunda feira dia 11 das 08:30 as 12h' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.93,
                data: [
                    'title' => 'Visita tecnica',
                    'type' => 'visita',
                    'date_text' => '11/05/2026',
                    'time_text' => 'das 08:30 as 12h0',
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, 'Data/hora: 11/05/2026 08:30'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('segunda feira dia 11 das 08:30 as 12h', 123456, 999001))
            ->assertOk();

        $selection = TelegramPendingSelection::query()
            ->where('company_id', $company->id)
            ->where('type', 'calendar_event_create')
            ->latest('id')
            ->first();

        $this->assertNotNull($selection);
        $payload = is_array($selection->payload) ? $selection->payload : [];
        $calendarEvent = is_array($payload['calendar_event'] ?? null) ? $payload['calendar_event'] : [];

        $this->assertSame('2026-05-11 08:30:00', $calendarEvent['starts_at'] ?? null);
        $this->assertSame('2026-05-11 12:00:00', $calendarEvent['ends_at'] ?? null);
    }

    public function test_original_message_fallback_extracts_date_and_time_when_ai_data_is_missing(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Fallback');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'marca para amanha ir a um cliente em alvarelhos das 8h30 as 12h0' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.84,
                data: [
                    'title' => 'Visita a cliente',
                    'type' => 'visita',
                    'date_text' => null,
                    'time_text' => null,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, 'Data/hora:')
                && ! str_contains((string) $message, '00:00'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('marca para amanha ir a um cliente em alvarelhos das 8h30 as 12h0', 123456, 999001))
            ->assertOk();
    }

    public function test_construction_site_from_other_company_is_not_used(): void
    {
        $companyA = $this->createCompany('Empresa Telegram Agenda Obra A');
        $companyB = $this->createCompany('Empresa Telegram Agenda Obra B');
        $userA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $userB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($companyA, $userA, 123456, '999001');
        $siteB = $this->createConstructionSite($companyB, $userB, 'Obra Empresa B');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'marca visita amanha as 10h na obra b' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.94,
                data: [
                    'title' => 'Visita obra B',
                    'type' => 'visita',
                    'date_text' => 'amanha',
                    'time_text' => '10h',
                    'construction_site_term' => (string) $siteB->code,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao encontrei obra para: '.$siteB->code);
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('marca visita amanha as 10h na obra b', 123456, 999001))
            ->assertOk();
    }

    public function test_assigned_user_from_other_company_is_not_used(): void
    {
        $companyA = $this->createCompany('Empresa Telegram Agenda User A');
        $companyB = $this->createCompany('Empresa Telegram Agenda User B');
        $userA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN, 'ricardo@empresa-a.test', 'Ricardo A');
        $userB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN, 'joao@empresa-b.test', 'Joao B');
        $link = $this->createLink($companyA, $userA, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'agenda instalacao quarta as 8h para o Joao B' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.89,
                data: [
                    'title' => 'Instalacao',
                    'type' => 'obra',
                    'date_text' => 'quarta',
                    'time_text' => '8h',
                    'assigned_user_term' => (string) $userB->name,
                ]
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao encontrei utilizador para: '.$userB->name);
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('agenda instalacao quarta as 8h para o Joao B', 123456, 999001))
            ->assertOk();
    }

    public function test_overlap_same_user_is_blocked_on_confirmation(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Overlap');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $start = now()->addDay()->setTime(10, 0);
        CalendarEvent::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Evento existente',
            'type' => CalendarEvent::TYPE_TASK,
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => CalendarEvent::PRIORITY_NORMAL,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'all_day' => false,
        ]);

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'calendar_event_create',
            'payload' => [
                'calendar_event' => [
                    'title' => 'Evento novo',
                    'description' => null,
                    'type' => CalendarEvent::TYPE_VISIT,
                    'status' => CalendarEvent::STATUS_PENDING,
                    'priority' => CalendarEvent::PRIORITY_NORMAL,
                    'starts_at' => $start->copy()->addMinutes(30)->toDateTimeString(),
                    'ends_at' => $start->copy()->addHour()->addMinutes(30)->toDateTimeString(),
                    'all_day' => false,
                    'user_id' => $user->id,
                    'customer_id' => null,
                    'supplier_id' => null,
                    'construction_site_id' => null,
                    'quote_id' => null,
                ],
            ],
            'expires_at' => now()->addMinutes(10),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'O utilizador ja tem uma tarefa/evento nesse horario.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('OK CRIAR', 123456, 999001))
            ->assertOk();

        $this->assertSame(1, CalendarEvent::query()->forCompany((int) $company->id)->count());
    }

    public function test_overlap_for_different_users_is_allowed(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Overlap Users');
        $user1 = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN, 'u1@example.test', 'Utilizador Um');
        $user2 = $this->createCompanyUser($company, User::ROLE_COMPANY_USER, 'u2@example.test', 'Utilizador Dois');
        $this->createLink($company, $user1, 123456, '999001');

        $start = now()->addDay()->setTime(14, 0);
        CalendarEvent::query()->create([
            'company_id' => $company->id,
            'user_id' => $user1->id,
            'created_by' => $user1->id,
            'title' => 'Evento user 1',
            'type' => CalendarEvent::TYPE_TASK,
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => CalendarEvent::PRIORITY_NORMAL,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'all_day' => false,
        ]);

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user1->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'calendar_event_create',
            'payload' => [
                'calendar_event' => [
                    'title' => 'Evento user 2',
                    'description' => null,
                    'type' => CalendarEvent::TYPE_MEETING,
                    'status' => CalendarEvent::STATUS_PENDING,
                    'priority' => CalendarEvent::PRIORITY_NORMAL,
                    'starts_at' => $start->copy()->addMinutes(30)->toDateTimeString(),
                    'ends_at' => $start->copy()->addHour()->addMinutes(30)->toDateTimeString(),
                    'all_day' => false,
                    'user_id' => $user2->id,
                    'customer_id' => null,
                    'supplier_id' => null,
                    'construction_site_id' => null,
                    'quote_id' => null,
                ],
            ],
            'expires_at' => now()->addMinutes(10),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001' && str_contains((string) $message, 'Evento criado com sucesso.'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('OK CRIAR', 123456, 999001))
            ->assertOk();

        $this->assertSame(2, CalendarEvent::query()->forCompany((int) $company->id)->count());
    }

    public function test_agenda_command_still_works_after_calendar_create_feature(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Regressao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao tem eventos na agenda para hoje.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/agenda hoje', 123456, 999001))
            ->assertOk();
    }

    public function test_budget_exceeded_does_not_block_local_calendar_fast_path(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Budget', [
            'ai_monthly_budget_eur' => 1.00,
            'ai_budget_warning_percent' => 80,
            'ai_budget_hard_stop_enabled' => true,
        ]);
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        AiUsageLog::query()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'source' => 'telegram_ai_intent',
            'model' => 'gpt-5.4-nano',
            'input_tokens' => 1,
            'output_tokens' => 1,
            'total_tokens' => 2,
            'estimated_cost_eur' => 1.20,
            'metadata' => null,
        ]);

        $executionMock = Mockery::mock(AiExecutionService::class);
        $executionMock->shouldNotReceive('executePrompt');
        $this->app->instance(AiExecutionService::class, $executionMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001'
                && str_contains((string) $message, 'Vou criar este evento:'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('marca visita amanha as 10h', 123456, 999001))
            ->assertOk();
    }

    public function test_confirmation_path_uses_calendar_event_service(): void
    {
        $company = $this->createCompany('Empresa Telegram Agenda Service');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'calendar_event_create',
            'payload' => [
                'calendar_event' => [
                    'title' => 'Evento mock',
                    'description' => null,
                    'type' => CalendarEvent::TYPE_TASK,
                    'status' => CalendarEvent::STATUS_PENDING,
                    'priority' => CalendarEvent::PRIORITY_NORMAL,
                    'starts_at' => now()->addDay()->setTime(16, 0)->toDateTimeString(),
                    'ends_at' => null,
                    'all_day' => false,
                    'user_id' => $user->id,
                    'customer_id' => null,
                    'supplier_id' => null,
                    'construction_site_id' => null,
                    'quote_id' => null,
                ],
            ],
            'expires_at' => now()->addMinutes(10),
        ]);

        $calendarServiceMock = Mockery::mock(CalendarEventService::class);
        $calendarServiceMock->shouldReceive('create')
            ->once()
            ->withArgs(fn (int $companyId, int $actorUserId, array $payload): bool =>
                $companyId === (int) $company->id
                && $actorUserId === (int) $user->id
                && ($payload['title'] ?? '') === 'Evento mock'
            )
            ->andReturn(CalendarEvent::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'created_by' => $user->id,
                'title' => 'Evento mock',
                'type' => CalendarEvent::TYPE_TASK,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => CalendarEvent::PRIORITY_NORMAL,
                'starts_at' => now()->addDay()->setTime(16, 0),
                'ends_at' => null,
                'all_day' => false,
            ]));
        $this->app->instance(CalendarEventService::class, $calendarServiceMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $message): bool => (string) $chatId === '999001' && str_contains((string) $message, 'Evento criado com sucesso.'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('OK CRIAR', 123456, 999001))
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

    private function createCompanyUser(Company $company, string $role, ?string $email = null, ?string $name = null): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => true,
            'name' => $name ?? 'User '.Str::upper(Str::random(5)),
            'email' => $email ?? Str::lower(Str::random(8)).'@example.test',
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

    private function createConstructionSite(Company $company, User $creator, string $name): ConstructionSite
    {
        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => $name.' Cliente',
            'is_active' => true,
        ]);

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
}
