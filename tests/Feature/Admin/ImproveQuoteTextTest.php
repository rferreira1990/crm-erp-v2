<?php

namespace Tests\Feature\Admin;

use App\DTO\Ai\AiResponseData;
use App\Exceptions\Ai\AiBudgetExceededException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use App\Services\Ai\AiExecutionService;
use App\Services\Ai\QuoteTextImproverService;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ImproveQuoteTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);

        config()->set('ai.enabled', true);
        config()->set('ai.monthly_budget_eur', null);
    }

    public function test_company_admin_can_improve_quote_text_successfully(): void
    {
        $company = $this->createCompany('Empresa AI Improve Success');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $mock = Mockery::mock(QuoteTextImproverService::class);
        $mock->shouldReceive('improve')
            ->once()
            ->andReturn([
                'improved_text' => 'Execucao de trabalhos de raspagem e envernizamento de pavimento em madeira.',
                'model' => 'gpt-5.4-nano',
                'input_tokens' => 50,
                'output_tokens' => 25,
                'total_tokens' => 75,
                'estimated_cost_eur' => 0.000020,
            ]);
        $this->app->instance(QuoteTextImproverService::class, $mock);

        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->postJson(route('admin.ai.improve-quote-text'), [
                'text' => 'raspar e envernizar chao',
            ]);

        $response->assertOk()
            ->assertJsonPath('improved_text', 'Execucao de trabalhos de raspagem e envernizamento de pavimento em madeira.')
            ->assertJsonPath('model', 'gpt-5.4-nano');
    }

    public function test_budget_exceeded_returns_422_with_friendly_message(): void
    {
        $company = $this->createCompany('Empresa AI Improve Budget');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $mock = Mockery::mock(QuoteTextImproverService::class);
        $mock->shouldReceive('improve')
            ->once()
            ->andThrow(new AiBudgetExceededException());
        $this->app->instance(QuoteTextImproverService::class, $mock);

        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->postJson(route('admin.ai.improve-quote-text'), [
                'text' => 'montagem quadro eletrico e tomadas',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Limite mensal de AI atingido. Aumente o orcamento ou aguarde pelo proximo mes.',
            ]);
    }

    public function test_text_is_required(): void
    {
        $company = $this->createCompany('Empresa AI Improve Validation');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->postJson(route('admin.ai.improve-quote-text'), [
                'text' => '',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['text']);
    }

    public function test_ai_exception_returns_friendly_message(): void
    {
        $company = $this->createCompany('Empresa AI Improve Exception');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $mock = Mockery::mock(QuoteTextImproverService::class);
        $mock->shouldReceive('improve')
            ->once()
            ->andThrow(new RuntimeException('OpenAI timeout'));
        $this->app->instance(QuoteTextImproverService::class, $mock);

        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->postJson(route('admin.ai.improve-quote-text'), [
                'text' => 'texto base',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Nao foi possivel melhorar o texto agora.',
            ]);
    }

    public function test_response_is_sanitized_and_usage_log_is_created(): void
    {
        $company = $this->createCompany('Empresa AI Improve Sanitize');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);

        $executionMock = Mockery::mock(AiExecutionService::class);
        $executionMock->shouldReceive('executePrompt')
            ->once()
            ->andReturn(new AiResponseData(
                text: "```text\n\"<script>alert(1)</script> Fornecimento e aplicacao de verniz.\"\n```",
                model: 'gpt-5.4-nano',
                inputTokens: 21,
                outputTokens: 14,
                totalTokens: 35,
                estimatedCostEur: 0.000012
            ));
        $this->app->instance(AiExecutionService::class, $executionMock);

        $response = $this->actingAs($admin)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->postJson(route('admin.ai.improve-quote-text'), [
                'text' => 'aplicar verniz',
            ]);

        $response->assertOk();
        $response->assertJsonPath('improved_text', 'alert(1) Fornecimento e aplicacao de verniz.');

        $this->assertDatabaseHas('ai_usage_logs', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'source' => 'improve_quote_text',
            'model' => 'gpt-5.4-nano',
            'input_tokens' => 21,
            'output_tokens' => 14,
            'total_tokens' => 35,
        ]);
    }

    public function test_user_without_permission_gets_forbidden(): void
    {
        $company = $this->createCompany('Empresa AI Improve Forbidden');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->postJson(route('admin.ai.improve-quote-text'), [
                'text' => 'texto de teste',
            ]);

        $response->assertForbidden();
    }

    public function test_quote_id_validation_is_scoped_by_company(): void
    {
        $companyA = $this->createCompany('Empresa AI Improve Scope A');
        $companyB = $this->createCompany('Empresa AI Improve Scope B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);
        $adminB = $this->createCompanyUser($companyB, User::ROLE_COMPANY_ADMIN);

        $quoteFromCompanyB = $this->createQuote($companyB, $adminB, 'Quote externa');

        $response = $this->actingAs($adminA)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->postJson(route('admin.ai.improve-quote-text'), [
                'text' => 'aplicar verniz e acabamento',
                'quote_id' => $quoteFromCompanyB->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quote_id']);
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

    private function createQuote(Company $company, User $user, string $customerName): Quote
    {
        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => $customerName,
            'is_active' => true,
        ]);

        return Quote::createWithGeneratedNumber((int) $company->id, [
            'version' => 1,
            'status' => Quote::STATUS_DRAFT,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
            'is_active' => true,
            'is_locked' => false,
            'assigned_user_id' => $user->id,
        ]);
    }
}
