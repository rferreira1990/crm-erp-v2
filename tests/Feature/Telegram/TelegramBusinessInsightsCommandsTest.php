<?php

namespace Tests\Feature\Telegram;

use App\DTO\Telegram\TelegramAiIntentData;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentItem;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentItem;
use App\Models\SalesDocumentReceipt;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TelegramPendingSelection;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Telegram\Commands\TelegramOverdueCustomersCommandService;
use App\Services\Telegram\TelegramAiIntentService;
use App\Services\Telegram\TelegramBotService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TelegramBusinessInsightsCommandsTest extends TestCase
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

    public function test_kpi_command_returns_summary(): void
    {
        $company = $this->createCompany('Empresa KPI');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $customer = $this->createCustomer($company, 'Cliente KPI');
        $supplier = $this->createSupplier($company, 'Fornecedor KPI');

        $salesDocument = $this->createSalesDocument($company, $user, $customer, 1000.0, now()->subDay()->toDateString());
        $this->createSalesReceipt($company, $user, $salesDocument, 250.0, now()->toDateString());

        $purchaseDocument = $this->createPurchaseDocument($company, $user, $supplier, 800.0, now()->subDays(3)->toDateString());
        $this->createSupplierPayment($company, $user, $purchaseDocument, 100.0, now()->toDateString());

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'KPI de hoje:')
                && str_contains((string) $text, 'Recebido:')
                && str_contains((string) $text, 'A pagar fornecedores:')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/kpi hoje', (int) $link->telegram_user_id, 999001))
            ->assertOk();
    }

    public function test_overdue_customers_command_is_company_scoped(): void
    {
        $companyA = $this->createCompany('Empresa Clientes Vencidos A');
        $companyB = $this->createCompany('Empresa Clientes Vencidos B');
        $userA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $userB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $linkA = $this->createLink($companyA, $userA, 123456, '999001');

        $customerA = $this->createCustomer($companyA, 'Cliente A');
        $customerB = $this->createCustomer($companyB, 'Cliente B');

        $this->createSalesDocument($companyA, $userA, $customerA, 500.0, now()->subDays(10)->toDateString());
        $this->createSalesDocument($companyB, $userB, $customerB, 900.0, now()->subDays(10)->toDateString());

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Cliente A')
                && ! str_contains((string) $text, 'Cliente B')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/clientes-vencidos', (int) $linkA->telegram_user_id, 999001))
            ->assertOk();
    }

    public function test_overdue_suppliers_command_is_company_scoped(): void
    {
        $companyA = $this->createCompany('Empresa Fornecedores Vencidos A');
        $companyB = $this->createCompany('Empresa Fornecedores Vencidos B');
        $userA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $userB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);
        $linkA = $this->createLink($companyA, $userA, 123456, '999001');

        $supplierA = $this->createSupplier($companyA, 'Fornecedor A');
        $supplierB = $this->createSupplier($companyB, 'Fornecedor B');

        $this->createPurchaseDocument($companyA, $userA, $supplierA, 750.0, now()->subDays(12)->toDateString());
        $this->createPurchaseDocument($companyB, $userB, $supplierB, 1250.0, now()->subDays(12)->toDateString());

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Fornecedor A')
                && ! str_contains((string) $text, 'Fornecedor B')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/fornecedores-vencidos', (int) $linkA->telegram_user_id, 999001))
            ->assertOk();
    }

    public function test_quote_followup_command_lists_old_pending_quotes(): void
    {
        $company = $this->createCompany('Empresa Followup');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $quote = $this->createQuote($company, $user, 'Cliente Followup', Quote::STATUS_SENT, 300.0, now()->subDays(9)->toDateString());
        $this->createQuote($company, $user, 'Cliente Recente', Quote::STATUS_SENT, 400.0, now()->subDays(2)->toDateString());

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Orcamentos sem resposta (follow-up):')
                && str_contains((string) $text, (string) $quote->number)
                && ! str_contains((string) $text, 'Cliente Recente')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/orcamentos-sem-resposta', (int) $link->telegram_user_id, 999001))
            ->assertOk();
    }

    public function test_help_command_contains_new_business_commands(): void
    {
        $company = $this->createCompany('Empresa Ajuda');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, '/kpi hoje|mes')
                && str_contains((string) $text, '/clientes-vencidos')
                && str_contains((string) $text, '/fornecedores-vencidos')
                && str_contains((string) $text, '/orcamentos-sem-resposta')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/ajuda', (int) $link->telegram_user_id, 999001))
            ->assertOk();
    }

    public function test_natural_language_overdue_customers_uses_ai_intent_and_command_service(): void
    {
        $company = $this->createCompany('Empresa NLP Vencidos');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_OVERDUE_CUSTOMERS_LOOKUP,
                term: null,
                confidence: 0.95
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $overdueCustomersMock = Mockery::mock(TelegramOverdueCustomersCommandService::class);
        $overdueCustomersMock->shouldReceive('execute')
            ->once()
            ->withArgs(fn (TelegramUserLink $givenLink, $chatId): bool => $givenLink->id === $link->id && (string) $chatId === '999001')
            ->andReturn('Top clientes com vencidos: ...');
        $this->app->instance(TelegramOverdueCustomersCommandService::class, $overdueCustomersMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Top clientes com vencidos: ...');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('quais os clientes com pagamentos vencidos?', (int) $link->telegram_user_id, 999001)
        )->assertOk();
    }

    public function test_clientes_vencidos_command_exposes_followup_instruction_and_creates_pending_selection(): void
    {
        $company = $this->createCompany('Empresa Followup Clientes');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');
        $customer = $this->createCustomer($company, 'Cliente Followup');

        $this->createSalesDocument($company, $user, $customer, 350.0, now()->subDays(12)->toDateString());

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'CRIAR FOLLOW-UP N')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/clientes-vencidos', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseHas('telegram_pending_selections', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'overdue_customer_followup',
        ]);
    }

    public function test_criar_followup_creates_calendar_pending_preview_and_ok_criar_creates_event(): void
    {
        $company = $this->createCompany('Empresa Followup Calendario');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $customer = $this->createCustomer($company, 'Cliente Cobrança');

        $this->createSalesDocument($company, $user, $customer, 890.0, now()->subDays(9)->toDateString());

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'CRIAR FOLLOW-UP N')
            );
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Vou criar este evento:')
                && str_contains((string) $text, 'OK CRIAR')
            );
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Evento criado com sucesso.')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/clientes-vencidos', 123456, 999001))
            ->assertOk();

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('CRIAR FOLLOW-UP 1', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseHas('telegram_pending_selections', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'calendar_event_create',
        ]);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('OK CRIAR', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseHas('calendar_events', [
            'company_id' => $company->id,
            'created_by' => $user->id,
            'customer_id' => $customer->id,
            'type' => CalendarEvent::TYPE_REMINDER,
            'priority' => CalendarEvent::PRIORITY_HIGH,
        ]);
    }

    public function test_criar_followup_without_calendar_permission_is_blocked(): void
    {
        $company = $this->createCompany('Empresa Followup Sem Permissao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        if ($user->hasPermissionTo('company.calendar.create')) {
            $user->revokePermissionTo('company.calendar.create');
        }
        $this->createLink($company, $user, 123456, '999001');
        $customer = $this->createCustomer($company, 'Cliente Sem Permissao');
        $this->createSalesDocument($company, $user, $customer, 600.0, now()->subDays(15)->toDateString());

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'overdue_customer_followup',
            'payload' => ['ids' => [$customer->id]],
            'expires_at' => now()->addMinutes(10),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao tem permissao para criar eventos na agenda.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('CRIAR FOLLOW-UP 1', 123456, 999001))
            ->assertOk();

        $this->assertDatabaseMissing('telegram_pending_selections', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'calendar_event_create',
        ]);

        $this->assertSame(0, CalendarEvent::query()->forCompany((int) $company->id)->count());
    }

    public function test_criar_followup_with_expired_selection_returns_friendly_message(): void
    {
        $company = $this->createCompany('Empresa Followup Expirada');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $customer = $this->createCustomer($company, 'Cliente Expirado');

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'overdue_customer_followup',
            'payload' => ['ids' => [$customer->id]],
            'expires_at' => now()->subMinute(),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Selecao expirada ou inexistente. Execute /clientes-vencidos novamente.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('CRIAR FOLLOW-UP 1', 123456, 999001))
            ->assertOk();
    }

    public function test_criar_followup_with_invalid_choice_returns_friendly_message(): void
    {
        $company = $this->createCompany('Empresa Followup Invalida');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');
        $customer = $this->createCustomer($company, 'Cliente Invalido');

        TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'overdue_customer_followup',
            'payload' => ['ids' => [$customer->id]],
            'expires_at' => now()->addMinutes(10),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Opcao invalida. Use o numero da lista atual de clientes vencidos.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('CRIAR FOLLOW-UP 9', 123456, 999001))
            ->assertOk();
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

    private function createSupplier(Company $company, string $name): Supplier
    {
        return Supplier::query()->create([
            'company_id' => $company->id,
            'supplier_type' => Supplier::TYPE_COMPANY,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createQuote(
        Company $company,
        User $user,
        string $customerName,
        string $status,
        float $total,
        string $issueDate
    ): Quote {
        $customer = $this->createCustomer($company, $customerName);

        return Quote::createWithGeneratedNumber((int) $company->id, [
            'version' => 1,
            'status' => $status,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'issue_date' => $issueDate,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'sent_at' => in_array($status, [Quote::STATUS_SENT, Quote::STATUS_VIEWED], true) ? now()->subDays(8) : null,
            'is_active' => true,
            'is_locked' => false,
        ]);
    }

    private function createSalesDocument(
        Company $company,
        User $user,
        Customer $customer,
        float $total,
        string $dueDate
    ): SalesDocument {
        $document = SalesDocument::createWithGeneratedNumber((int) $company->id, [
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'status' => SalesDocument::STATUS_ISSUED,
            'payment_status' => SalesDocument::PAYMENT_STATUS_UNPAID,
            'issue_date' => now()->subDays(15)->toDateString(),
            'due_date' => $dueDate,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'issued_at' => now()->subDays(15),
            'created_by' => $user->id,
        ]);

        SalesDocumentItem::query()->create([
            'company_id' => $company->id,
            'sales_document_id' => $document->id,
            'line_order' => 1,
            'description' => 'Linha teste',
            'quantity' => 1,
            'unit_price' => $total,
            'discount_percent' => 0,
            'line_subtotal' => $total,
            'line_discount_total' => 0,
            'tax_rate' => 0,
            'line_tax_total' => 0,
            'line_total' => $total,
        ]);

        $document->recalculateTotalsFromItems();

        return $document->fresh();
    }

    private function createSalesReceipt(
        Company $company,
        User $user,
        SalesDocument $document,
        float $amount,
        string $receiptDate
    ): SalesDocumentReceipt {
        return SalesDocumentReceipt::createWithGeneratedNumber((int) $company->id, [
            'sales_document_id' => $document->id,
            'customer_id' => $document->customer_id,
            'receipt_date' => $receiptDate,
            'amount' => $amount,
            'status' => SalesDocumentReceipt::STATUS_ISSUED,
            'issued_at' => now(),
            'created_by' => $user->id,
        ]);
    }

    private function createPurchaseDocument(
        Company $company,
        User $user,
        Supplier $supplier,
        float $total,
        string $dueDate
    ): PurchaseDocument {
        $document = PurchaseDocument::createWithGeneratedNumber((int) $company->id, [
            'supplier_id' => $supplier->id,
            'status' => PurchaseDocument::STATUS_CONFIRMED,
            'payment_status' => PurchaseDocument::PAYMENT_STATUS_UNPAID,
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => $dueDate,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'confirmed_at' => now()->subDays(20),
            'created_by' => $user->id,
        ]);

        PurchaseDocumentItem::query()->create([
            'company_id' => $company->id,
            'purchase_document_id' => $document->id,
            'line_order' => 1,
            'description' => 'Linha teste',
            'quantity' => 1,
            'unit_price' => $total,
            'discount_percent' => 0,
            'line_subtotal' => $total,
            'line_discount_total' => 0,
            'tax_rate' => 0,
            'line_tax_total' => 0,
            'line_total' => $total,
        ]);

        $document->recalculateTotalsFromItems();

        return $document->fresh();
    }

    private function createSupplierPayment(
        Company $company,
        User $user,
        PurchaseDocument $document,
        float $amount,
        string $paymentDate
    ): SupplierPayment {
        return SupplierPayment::createWithGeneratedNumber((int) $company->id, [
            'purchase_document_id' => $document->id,
            'supplier_id' => $document->supplier_id,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'status' => SupplierPayment::STATUS_ISSUED,
            'issued_at' => now(),
            'created_by' => $user->id,
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
