<?php

namespace Tests\Feature\Telegram;

use App\DTO\Telegram\TelegramAiIntentData;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PurchaseDocument;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\TelegramPendingSelection;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Models\SalesDocument;
use App\Models\SalesDocumentItem;
use App\Services\Telegram\Commands\TelegramPendingQuotesCommandService;
use App\Services\Telegram\Commands\TelegramQuoteInfoCommandService;
use App\Services\Telegram\Commands\TelegramCustomerBalanceCommandService;
use App\Services\Telegram\Commands\TelegramSupplierBalanceCommandService;
use App\Services\Telegram\TelegramAiIntentService;
use App\Services\Telegram\TelegramBotService;
use App\Models\PurchaseDocumentItem;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TelegramAdvancedReadOnlyCommandsTest extends TestCase
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

    public function test_pending_quotes_command_is_company_scoped(): void
    {
        $companyA = $this->createCompany('Empresa Telegram Pendentes A');
        $companyB = $this->createCompany('Empresa Telegram Pendentes B');
        $userA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $this->createLink($companyA, $userA, 123456, '999001');

        $quoteA = $this->createQuote($companyA, $userA, 'Cliente A', Quote::STATUS_SENT, 500);
        $this->createQuote($companyB, $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN), 'Cliente B', Quote::STATUS_SENT, 700);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text) use ($quoteA): bool {
                return (string) $chatId === '999001'
                    && str_contains((string) $text, (string) $quoteA->number)
                    && str_contains((string) $text, 'Cliente A')
                    && ! str_contains((string) $text, 'Cliente B');
            });
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/orcamentos-pendentes', 123456, 999001))
            ->assertOk();
    }

    public function test_quote_info_single_result_sends_summary_and_pdf(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Telegram Orcamento Single');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $quote = $this->createQuote($company, $user, 'Cliente PDF', Quote::STATUS_SENT, 1500);
        $pdfRelativePath = 'quotes/'.$company->id.'/'.$quote->id.'/pdf/teste.pdf';
        Storage::disk('local')->put($pdfRelativePath, 'pdf');
        $quote->forceFill(['pdf_path' => $pdfRelativePath])->save();

        $expectedPath = Storage::disk('local')->path($pdfRelativePath);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool => (string) $chatId === '999001' && str_contains((string) $text, (string) $quote->number));
        $botMock->shouldReceive('sendDocument')
            ->once()
            ->with(999001, $expectedPath, 'PDF do orçamento '.$quote->number);
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/orcamento '.$quote->number, 123456, 999001)
        )->assertOk();
    }

    public function test_quote_info_multiple_results_creates_pending_selection(): void
    {
        $company = $this->createCompany('Empresa Telegram Orcamento Multi');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $this->createQuote($company, $user, 'Anjo Lima', Quote::STATUS_SENT, 100);
        $this->createQuote($company, $user, 'Anjo Lima', Quote::STATUS_VIEWED, 200);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool => (string) $chatId === '999001' && str_contains((string) $text, 'Responda com o numero do orcamento.'));
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/orcamento Anjo', 123456, 999001)
        )->assertOk();

        $this->assertDatabaseHas('telegram_pending_selections', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'quote_info',
        ]);
    }

    public function test_customer_quotes_command_lists_customer_quotes_and_creates_pending_selection(): void
    {
        $company = $this->createCompany('Empresa Telegram Orcamentos Cliente');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $this->createQuote($company, $user, 'Anjo Lima', Quote::STATUS_SENT, 350);
        $this->createQuote($company, $user, 'Anjo Lima', Quote::STATUS_VIEWED, 780);
        $this->createQuote($company, $user, 'Outro Cliente', Quote::STATUS_SENT, 120);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Orcamentos do cliente: Anjo')
                && str_contains((string) $text, 'Responda com o numero do orcamento.')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/orcamentos-cliente Anjo', 123456, 999001)
        )->assertOk();

        $pending = TelegramPendingSelection::query()
            ->where('company_id', $company->id)
            ->where('telegram_user_id', 123456)
            ->where('type', 'quote_info')
            ->latest('id')
            ->first();

        $this->assertNotNull($pending);
        $payload = is_array($pending->payload) ? $pending->payload : [];
        $ids = $payload['ids'] ?? [];
        $this->assertIsArray($ids);
        $this->assertCount(2, $ids);
    }

    public function test_customer_quotes_command_without_term_returns_usage_message(): void
    {
        $company = $this->createCompany('Empresa Telegram Orcamentos Cliente Empty');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Use: /orcamentos-cliente NOME-CLIENTE');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('/orcamentos-cliente', 123456, 999001)
        )->assertOk();
    }

    public function test_numeric_reply_consumes_pending_selection_and_sends_selected_quote_pdf(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Telegram Selecao');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $quoteA = $this->createQuote($company, $user, 'Cliente A', Quote::STATUS_SENT, 100);
        $quoteB = $this->createQuote($company, $user, 'Cliente B', Quote::STATUS_SENT, 200);

        $pdfA = 'quotes/'.$company->id.'/'.$quoteA->id.'/pdf/a.pdf';
        $pdfB = 'quotes/'.$company->id.'/'.$quoteB->id.'/pdf/b.pdf';
        Storage::disk('local')->put($pdfA, 'pdf-a');
        Storage::disk('local')->put($pdfB, 'pdf-b');
        $quoteA->forceFill(['pdf_path' => $pdfA])->save();
        $quoteB->forceFill(['pdf_path' => $pdfB])->save();

        $pending = TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'quote_info',
            'payload' => ['ids' => [$quoteB->id, $quoteA->id]],
            'expires_at' => now()->addMinutes(10),
        ]);

        $expectedPath = Storage::disk('local')->path($pdfB);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool => (string) $chatId === '999001' && str_contains((string) $text, (string) $quoteB->number));
        $botMock->shouldReceive('sendDocument')
            ->once()
            ->with(999001, $expectedPath, 'PDF do orçamento '.$quoteB->number);
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('1', 123456, 999001))
            ->assertOk();

        $this->assertNotNull($pending->fresh()->consumed_at);
    }

    public function test_expired_pending_selection_returns_friendly_message(): void
    {
        $company = $this->createCompany('Empresa Telegram Selecao Expirada');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $pending = TelegramPendingSelection::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'telegram_user_id' => 123456,
            'telegram_chat_id' => '999001',
            'type' => 'quote_info',
            'payload' => ['ids' => [123]],
            'expires_at' => now()->subMinute(),
        ]);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Selecao expirada. Faca o pedido novamente.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('1', 123456, 999001))
            ->assertOk();

        $this->assertNotNull($pending->fresh()->consumed_at);
    }

    public function test_customer_balance_command_returns_same_company_customer_data(): void
    {
        $company = $this->createCompany('Empresa Telegram Cliente Saldo');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $customer = $this->createCustomer($company, 'Anjo Lima');
        $document = $this->createSalesDocument($company, $user, $customer, 1200, now()->subDays(15)->toDateString());
        $this->assertDatabaseHas('sales_documents', [
            'id' => $document->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'status' => SalesDocument::STATUS_ISSUED,
            'payment_status' => SalesDocument::PAYMENT_STATUS_UNPAID,
            'grand_total' => 1200,
        ]);
        $directMessage = app(TelegramCustomerBalanceCommandService::class)->execute($link, '999001', 'Anjo');
        $this->assertSame(1, SalesDocument::query()
            ->forCompany((int) $company->id)
            ->where('customer_id', $customer->id)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->count());
        $this->assertStringContainsString('1.200,00', $directMessage['message']);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Cliente: Anjo Lima')
                && str_contains((string) $text, 'Saldo em aberto: 1.200,00 €')
                && str_contains((string) $text, 'Docs vencidos: 1')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/cliente-saldo Anjo', 123456, 999001))
            ->assertOk();
    }

    public function test_customer_balance_command_does_not_show_other_company_customer(): void
    {
        $companyA = $this->createCompany('Empresa Telegram Cliente A');
        $companyB = $this->createCompany('Empresa Telegram Cliente B');
        $userA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $this->createLink($companyA, $userA, 123456, '999001');

        $this->createCustomer($companyB, 'Cliente Exclusivo B');

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao encontrei clientes para: Cliente');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/cliente-saldo Cliente', 123456, 999001))
            ->assertOk();
    }

    public function test_supplier_balance_command_returns_same_company_supplier_data(): void
    {
        $company = $this->createCompany('Empresa Telegram Fornecedor Saldo');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $supplier = $this->createSupplier($company, 'ABC Fornecedor');
        $document = $this->createPurchaseDocument($company, $user, $supplier, 900, now()->subDays(8)->toDateString());
        $this->assertDatabaseHas('purchase_documents', [
            'id' => $document->id,
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseDocument::STATUS_CONFIRMED,
            'payment_status' => PurchaseDocument::PAYMENT_STATUS_UNPAID,
            'grand_total' => 900,
        ]);
        $directMessage = app(TelegramSupplierBalanceCommandService::class)->execute($link, '999001', 'ABC');
        $this->assertSame(1, PurchaseDocument::query()
            ->forCompany((int) $company->id)
            ->where('supplier_id', $supplier->id)
            ->where('status', PurchaseDocument::STATUS_CONFIRMED)
            ->count());
        $this->assertStringContainsString('900,00', $directMessage['message']);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, $text): bool =>
                (string) $chatId === '999001'
                && str_contains((string) $text, 'Fornecedor: ABC Fornecedor')
                && str_contains((string) $text, 'Saldo em aberto: 900,00 €')
                && str_contains((string) $text, 'Docs vencidos: 1')
            );
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/fornecedor-saldo ABC', 123456, 999001))
            ->assertOk();
    }

    public function test_natural_language_pending_quotes_uses_ai_intent_and_command_service(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Pendentes');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool => $text === 'que orcamentos aguardam resposta?' && $givenLink->id === $link->id)
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_PENDING_QUOTES_LOOKUP,
                term: null,
                confidence: 0.96
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $pendingQuotesMock = Mockery::mock(TelegramPendingQuotesCommandService::class);
        $pendingQuotesMock->shouldReceive('execute')
            ->once()
            ->withArgs(fn (TelegramUserLink $givenLink): bool => $givenLink->id === $link->id)
            ->andReturn('Orcamentos a aguardar resposta: ...');
        $this->app->instance(TelegramPendingQuotesCommandService::class, $pendingQuotesMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Orcamentos a aguardar resposta: ...');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('que orcamentos aguardam resposta?', 123456, 999001))
            ->assertOk();
    }

    public function test_natural_language_customer_quotes_uses_ai_intent_and_quote_service(): void
    {
        $company = $this->createCompany('Empresa Telegram AI Orcamentos Cliente');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $link = $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldReceive('detect')
            ->once()
            ->withArgs(fn (string $text, TelegramUserLink $givenLink): bool =>
                $text === 'lista os orcamentos do cliente anjo lima'
                && $givenLink->id === $link->id
            )
            ->andReturn(new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CUSTOMER_QUOTES_LOOKUP,
                term: 'Anjo Lima',
                confidence: 0.95
            ));
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $quoteInfoMock = Mockery::mock(TelegramQuoteInfoCommandService::class);
        $quoteInfoMock->shouldReceive('executeByCustomerTerm')
            ->once()
            ->withArgs(fn (TelegramUserLink $givenLink, $chatId, string $term): bool =>
                $givenLink->id === $link->id
                && (string) $chatId === '999001'
                && $term === 'Anjo Lima'
            )
            ->andReturn([
                'message' => 'Encontrei varios orcamentos:',
                'pdf_path' => null,
                'pdf_caption' => null,
            ]);
        $this->app->instance(TelegramQuoteInfoCommandService::class, $quoteInfoMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Encontrei varios orcamentos:');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(
            route('telegram.webhook', ['secret' => 'valid-secret']),
            $this->messagePayload('lista os orcamentos do cliente anjo lima', 123456, 999001)
        )->assertOk();
    }

    public function test_direct_commands_do_not_call_ai_intent_service(): void
    {
        $company = $this->createCompany('Empresa Telegram Sem AI Direto');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $this->createLink($company, $user, 123456, '999001');

        $intentMock = Mockery::mock(TelegramAiIntentService::class);
        $intentMock->shouldNotReceive('detect');
        $this->app->instance(TelegramAiIntentService::class, $intentMock);

        $pendingQuotesMock = Mockery::mock(TelegramPendingQuotesCommandService::class);
        $pendingQuotesMock->shouldReceive('execute')
            ->once()
            ->andReturn('Nao existem orcamentos a aguardar resposta.');
        $this->app->instance(TelegramPendingQuotesCommandService::class, $pendingQuotesMock);

        $botMock = Mockery::mock(TelegramBotService::class);
        $botMock->shouldReceive('sendMessage')
            ->once()
            ->with(999001, 'Nao existem orcamentos a aguardar resposta.');
        $this->app->instance(TelegramBotService::class, $botMock);

        $this->postJson(route('telegram.webhook', ['secret' => 'valid-secret']), $this->messagePayload('/orcamentos-pendentes', 123456, 999001))
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
        float $total
    ): Quote {
        $customer = $this->createCustomer($company, $customerName);

        $quote = Quote::createWithGeneratedNumber((int) $company->id, [
            'version' => 1,
            'status' => $status,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'issue_date' => now()->subDays(2)->toDateString(),
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'sent_at' => $status === Quote::STATUS_SENT || $status === Quote::STATUS_VIEWED ? now()->subDay() : null,
            'is_active' => true,
            'is_locked' => false,
        ]);

        $quote->forceFill([
            'customer_name' => $customer->name,
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        return $quote;
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
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => $dueDate,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'issued_at' => now()->subDays(20),
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
        $document->forceFill([
            'status' => SalesDocument::STATUS_ISSUED,
            'payment_status' => SalesDocument::PAYMENT_STATUS_UNPAID,
            'due_date' => $dueDate,
        ])->save();

        return $document->fresh();
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
            'issue_date' => now()->subDays(12)->toDateString(),
            'due_date' => $dueDate,
            'currency' => 'EUR',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'confirmed_at' => now()->subDays(12),
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
        $document->forceFill([
            'status' => PurchaseDocument::STATUS_CONFIRMED,
            'payment_status' => PurchaseDocument::PAYMENT_STATUS_UNPAID,
            'due_date' => $dueDate,
        ])->save();

        return $document->fresh();
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
