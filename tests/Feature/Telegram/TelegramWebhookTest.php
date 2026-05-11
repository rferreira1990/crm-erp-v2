<?php

namespace Tests\Feature\Telegram;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telegram.enabled', true);
        config()->set('telegram.bot_token', 'test-bot-token');
        config()->set('telegram.webhook_secret', 'valid-secret');
        config()->set('telegram.allowed_user_ids', [123456]);
    }

    public function test_webhook_returns_404_when_bot_is_disabled(): void
    {
        config()->set('telegram.enabled', false);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), [])
            ->assertNotFound();
    }

    public function test_webhook_returns_404_when_secret_is_invalid(): void
    {
        $this->postJson(route('telegram.webhook', ['secret' => 'wrong-secret']), [])
            ->assertNotFound();
    }

    public function test_ping_command_for_authorized_user_replies_with_pong(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'pong');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/ping'))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_id_command_for_authorized_user_replies_with_ids(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, "Telegram user id: 123456\nChat id: 999001");
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/id'))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_ajuda_command_for_authorized_user_replies_with_commands_list(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Ajuda do Bot CRM/ERP')
                && str_contains((string) $text, '/stock TERMO')
                && str_contains((string) $text, '/orcamentos-cliente NOME-CLIENTE')
                && str_contains((string) $text, '/email xpto@exemplo.pt')
            );
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/ajuda'))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_unauthorized_user_gets_block_message(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Utilizador Telegram nao autorizado.');
        $this->app->instance(TelegramBotService::class, $mock);

        $payload = $this->messagePayload('/ping');
        $payload['message']['from']['id'] = 987654;

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_payload_without_text_returns_ok_without_sending_message(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldNotReceive('sendMessage');
        $this->app->instance(TelegramBotService::class, $mock);

        $payload = $this->messagePayload('/ping');
        unset($payload['message']['text']);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_regular_text_without_link_prompts_for_link_code(): void
    {
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Conta Telegram nao ligada. Use /link CODIGO.');
        $this->app->instance(TelegramBotService::class, $mock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('ola bot'))
            ->assertOk()
            ->assertJson(['ok' => true]);
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
}
